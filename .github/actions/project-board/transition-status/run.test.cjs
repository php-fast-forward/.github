'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');

const transitionStatus = require('./run.cjs');

const project = {
    id: 'project-1',
    title: 'PHP Fast Forward Project',
    fields: {
        nodes: [
            {
                __typename: 'ProjectV2SingleSelectField',
                id: 'status-field',
                name: 'Status',
                options: [
                    {
                        id: 'released-option',
                        name: 'Released',
                    },
                ],
            },
        ],
    },
};

/**
 * @param {string} id
 * @param {string} status
 * @param {'Issue'|'PullRequest'} type
 * @param {number} number
 * @param {string} repository
 *
 * @returns {object}
 */
function projectItem(id, status, type = 'Issue', number = 1, repository = 'php-fast-forward/dev-tools') {
    return {
        id,
        content: {
            __typename: type,
            number,
            repository: {
                nameWithOwner: repository,
            },
        },
        project: {
            id: project.id,
        },
        fieldValues: {
            nodes: [
                {
                    __typename: 'ProjectV2ItemFieldSingleSelectValue',
                    field: {
                        name: 'Status',
                    },
                    name: status,
                },
            ],
        },
    };
}

/**
 * @param {Array<object>} projectItems
 *
 * @returns {{github: {graphql: Function}, mutations: Array<object>}}
 */
function createGithub(projectItems) {
    const mutations = [];
    const github = {
        graphql: async (query, variables) => {
            if (query.includes('updateProjectV2ItemFieldValue')) {
                mutations.push(variables);

                return {
                    updateProjectV2ItemFieldValue: {
                        projectV2Item: {
                            id: variables.itemId,
                        },
                    },
                };
            }

            if (query.includes('node(id: $project)')) {
                return {
                    node: {
                        items: {
                            pageInfo: {
                                hasNextPage: false,
                                endCursor: null,
                            },
                            nodes: projectItems,
                        },
                    },
                };
            }

            if (query.includes('projectV2(number: $number)')) {
                return {
                    organization: {
                        projectV2: project,
                    },
                    user: {
                        projectV2: null,
                    },
                };
            }

            throw new Error(`Unexpected GraphQL operation: ${query}`);
        },
    };

    return {
        github,
        mutations,
    };
}

/**
 * @returns {{info: Array<string>, outputs: Record<string, string>, core: object}}
 */
function createCore() {
    const info = [];
    const outputs = {};

    return {
        info,
        outputs,
        core: {
            info: (message) => info.push(message),
            setOutput: (name, value) => {
                outputs[name] = value;
            },
        },
    };
}

/**
 * @param {Record<string, string>} env
 * @param {Function} callback
 *
 * @returns {Promise<void>}
 */
async function withEnvironment(env, callback) {
    const previous = {};

    for (const key of Object.keys(env)) {
        previous[key] = process.env[key];
        process.env[key] = env[key];
    }

    try {
        await callback();
    } finally {
        for (const [key, value] of Object.entries(previous)) {
            if (undefined === value) {
                delete process.env[key];

                continue;
            }

            process.env[key] = value;
        }
    }
}

test('moves items from multiple source statuses to the release status', async () => {
    const projectItems = [
        projectItem('current-pr', 'Release Prepared', 'PullRequest', 10),
        projectItem('merged-pr', 'Merged', 'PullRequest', 9),
        projectItem('foreign-merged-pr', 'Merged', 'PullRequest', 7, 'php-fast-forward/enum'),
        projectItem('backlog-issue', 'Backlog', 'Issue', 8),
    ];
    const {github, mutations} = createGithub(projectItems);
    const {core, outputs} = createCore();

    await withEnvironment({
        INPUT_INCLUDE_CURRENT_PULL_REQUEST: 'true',
        INPUT_FROM_STATUS: '',
        INPUT_FROM_STATUSES: 'Release Prepared,Merged',
        INPUT_TO_STATUS: 'Released',
        INPUT_PROJECT: '1',
    }, async () => {
        await transitionStatus({
            github,
            context: {
                repo: {
                    owner: 'php-fast-forward',
                    repo: 'dev-tools',
                },
                payload: {
                    pull_request: {
                        number: 10,
                    },
                },
            },
            core,
        });
    });

    assert.deepEqual(mutations.map((mutation) => mutation.itemId), ['current-pr', 'merged-pr']);
    assert.equal(outputs['source-statuses'], 'Release Prepared,Merged');
    assert.equal(outputs['moved-count'], '2');
    assert.equal(outputs['skipped-count'], '2');
});

test('keeps the legacy from-status input supported', async () => {
    const projectItems = [
        projectItem('merged-issue', 'Merged', 'Issue', 8),
    ];
    const {github, mutations} = createGithub(projectItems);
    const {core, outputs} = createCore();

    await withEnvironment({
        INPUT_INCLUDE_CURRENT_PULL_REQUEST: 'false',
        INPUT_FROM_STATUS: 'Merged',
        INPUT_FROM_STATUSES: '',
        INPUT_TO_STATUS: 'Released',
        INPUT_PROJECT: '1',
    }, async () => {
        await transitionStatus({
            github,
            context: {
                repo: {
                    owner: 'php-fast-forward',
                    repo: 'dev-tools',
                },
                payload: {},
            },
            core,
        });
    });

    assert.deepEqual(mutations.map((mutation) => mutation.itemId), ['merged-issue']);
    assert.equal(outputs['source-statuses'], 'Merged');
    assert.equal(outputs['moved-count'], '1');
    assert.equal(outputs['skipped-count'], '0');
});
