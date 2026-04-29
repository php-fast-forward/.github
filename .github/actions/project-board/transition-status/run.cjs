const board = require('../shared/project-board-client.cjs');

/**
 * @param {{github: import('@actions/github/lib/utils').GitHub, context: any, core: any}} deps
 *
 * @returns {Promise<void>}
 */
module.exports = async function transitionStatus({ github, context, core }) {
    const includeCurrentPullRequest = 'true' === (process.env.INPUT_INCLUDE_CURRENT_PULL_REQUEST ?? '').toLowerCase();
    const sourceStatuses = [
        ...(process.env.INPUT_FROM_STATUSES ?? '').split(','),
        process.env.INPUT_FROM_STATUS ?? '',
    ]
        .map((status) => status.trim())
        .filter((status, index, statuses) => '' !== status && statuses.indexOf(status) === index);
    const toStatus = process.env.INPUT_TO_STATUS;

    core.setOutput('source-statuses', sourceStatuses.join(','));

    if (0 === sourceStatuses.length) {
        core.info('No source project statuses were provided. Skipping status transition.');
        core.setOutput('moved-count', '0');
        core.setOutput('skipped-count', '0');

        return;
    }

    const project = await board.loadConfiguredProject(
        github,
        context.repo.owner,
        process.env.INPUT_PROJECT,
    );

    if (!project) {
        core.info('No configured GitHub Project V2 was resolved. Skipping status transition.');
        core.setOutput('moved-count', '0');
        core.setOutput('skipped-count', '0');

        return;
    }

    const statusField = board.getSingleSelectField(project, 'Status');
    const targetOption = board.getSingleSelectOption(project, 'Status', toStatus);

    if (!statusField || !targetOption) {
        core.info(`Project "${project.title}" does not expose the expected target status "${toStatus}".`);
        core.setOutput('moved-count', '0');
        core.setOutput('skipped-count', '0');

        return;
    }

    const loadProjectItems = async () => {
        const items = [];
        let cursor = null;

        do {
            const result = await github.graphql(
                `query($project: ID!, $cursor: String) {
                  node(id: $project) {
                    ... on ProjectV2 {
                      items(first: 100, after: $cursor) {
                        pageInfo {
                          hasNextPage
                          endCursor
                        }
                        nodes {
                          id
                          content {
                            __typename
                            ... on Issue {
                              number
                              repository {
                                nameWithOwner
                              }
                              title
                              url
                            }
                            ... on PullRequest {
                              number
                              repository {
                                nameWithOwner
                              }
                              title
                              url
                            }
                          }
                          fieldValues(first: 20) {
                            nodes {
                              __typename
                              ... on ProjectV2ItemFieldSingleSelectValue {
                                field {
                                  ... on ProjectV2SingleSelectField {
                                    name
                                  }
                                }
                                name
                              }
                            }
                          }
                        }
                      }
                    }
                  }
                }`,
                {
                    project: project.id,
                    cursor,
                },
            );

            const page = result.node?.items;

            items.push(...(page?.nodes ?? []));
            cursor = page?.pageInfo?.hasNextPage ? page.pageInfo.endCursor : null;
        } while (null !== cursor);

        return items;
    };

    const formatLabel = (item) => {
        const content = item.content;

        if ('Issue' === content?.__typename) {
            return `Issue #${content.number}`;
        }

        if ('PullRequest' === content?.__typename) {
            return `PR #${content.number}`;
        }

        return `Project item ${item.id}`;
    };

    const belongsToCurrentRepository = (item) => {
        const repository = item.content?.repository?.nameWithOwner;

        return `${context.repo.owner}/${context.repo.repo}` === repository;
    };

    const moveToStatus = async (item, label) => {
        const currentStatus = board.getExistingFieldValue(item, 'Status');

        if (!sourceStatuses.includes(currentStatus)) {
            return false;
        }

        await board.updateSingleSelectField(
            github,
            project.id,
            item.id,
            statusField.id,
            targetOption.id,
        );

        core.info(`${label} moved from ${currentStatus} to ${toStatus}.`);

        return true;
    };

    if (includeCurrentPullRequest) {
        core.info('The include-current-pull-request input is kept for compatibility; project item pagination already includes the current pull request when it is on the board.');
    }

    let movedCount = 0;
    let skippedCount = 0;

    for (const item of await loadProjectItems()) {
        if (!belongsToCurrentRepository(item)) {
            skippedCount++;

            continue;
        }

        if (await moveToStatus(item, formatLabel(item))) {
            movedCount++;

            continue;
        }

        skippedCount++;
    }

    core.info(`${movedCount} project item(s) moved to ${toStatus}; ${skippedCount} inspected item(s) skipped.`);
    core.setOutput('moved-count', String(movedCount));
    core.setOutput('skipped-count', String(skippedCount));
};
