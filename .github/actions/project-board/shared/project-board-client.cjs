/**
 * Fast Forward Development Tools for PHP projects.
 *
 * This file is part of fast-forward/dev-tools project.
 *
 * (c) Mentor do Nerd <contato@mentordosnerds.com.br>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

'use strict';

/**
 * Parses the first linked issue number from a pull request title/body pair.
 *
 * @param {string} text
 *
 * @returns {number|null}
 */
function parseLinkedIssueNumber(text) {
    const match = text.match(/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?|address(?:e[sd])?)\s+#(\d+)/i);

    if (!match) {
        return null;
    }

    return Number(match[1]);
}

/**
 * Loads the configured GitHub Project for the repository workflow.
 *
 * @param {import('@actions/github/lib/utils').GitHub} github
 * @param {string} projectOwner
 * @param {number|string} projectNumber
 *
 * @returns {Promise<object|null>}
 */
async function loadConfiguredProject(github, projectOwner, projectNumber) {
    if (!projectOwner) {
        return null;
    }

    if (!projectNumber) {
        if ('php-fast-forward' !== projectOwner) {
            return null;
        }

        const fallback = await github.graphql(
            `query($owner: String!) {
              organization(login: $owner) {
                projectsV2(first: 1, orderBy: {field: TITLE, direction: ASC}) {
                  nodes {
                    id
                    number
                    title
                    fields(first: 50) {
                      nodes {
                        __typename
                        ... on ProjectV2SingleSelectField {
                          id
                          name
                          options {
                            id
                            name
                            color
                            description
                          }
                        }
                      }
                    }
                  }
                }
              }
            }`,
            {
                owner: projectOwner,
            },
        );

        return fallback.organization?.projectsV2?.nodes?.[0] ?? null;
    }

    const result = await github.graphql(
        `query($owner: String!, $number: Int!) {
          organization(login: $owner) {
            projectV2(number: $number) {
              id
              title
              fields(first: 50) {
                nodes {
                  __typename
                  ... on ProjectV2SingleSelectField {
                    id
                    name
                    options {
                      id
                      name
                      color
                      description
                    }
                  }
                }
              }
            }
          }
          user(login: $owner) {
            projectV2(number: $number) {
              id
              title
              fields(first: 50) {
                nodes {
                  __typename
                  ... on ProjectV2SingleSelectField {
                    id
                    name
                    options {
                      id
                      name
                      color
                      description
                    }
                  }
                }
              }
            }
          }
        }`,
        {
            owner: projectOwner,
            number: Number(projectNumber),
        },
    );

    return result.organization?.projectV2 ?? result.user?.projectV2 ?? null;
}

/**
 * Returns the single-select field by name when present.
 *
 * @param {object} project
 * @param {string} fieldName
 *
 * @returns {object|null}
 */
function getSingleSelectField(project, fieldName) {
    return project.fields.nodes.find(
        (field) => 'ProjectV2SingleSelectField' === field.__typename && field.name === fieldName,
    ) ?? null;
}

/**
 * Resolves the option metadata for the named single-select value.
 *
 * @param {object} project
 * @param {string} fieldName
 * @param {string} optionName
 *
 * @returns {object|null}
 */
function getSingleSelectOption(project, fieldName, optionName) {
    const field = getSingleSelectField(project, fieldName);

    if (!field) {
        return null;
    }

    return field.options.find((option) => option.name === optionName) ?? null;
}

/**
 * Returns the existing project item field value by field name.
 *
 * @param {object} item
 * @param {string} fieldName
 *
 * @returns {string|null}
 */
function getExistingFieldValue(item, fieldName) {
    if (!item?.fieldValues?.nodes) {
        return null;
    }

    for (const node of item.fieldValues.nodes) {
        if ('ProjectV2ItemFieldSingleSelectValue' === node.__typename && node.field?.name === fieldName) {
            return node.name;
        }
    }

    return null;
}

/**
 * Updates a single-select field value by option id.
 *
 * @param {import('@actions/github/lib/utils').GitHub} github
 * @param {string} projectId
 * @param {string} itemId
 * @param {string} fieldId
 * @param {string} optionId
 *
 * @returns {Promise<void>}
 */
async function updateSingleSelectField(github, projectId, itemId, fieldId, optionId) {
    await github.graphql(
        `mutation($projectId: ID!, $itemId: ID!, $fieldId: ID!, $optionId: String!) {
          updateProjectV2ItemFieldValue(
            input: {
              projectId: $projectId,
              itemId: $itemId,
              fieldId: $fieldId,
              value: {singleSelectOptionId: $optionId}
            }
          ) {
            projectV2Item {
              id
            }
          }
        }`,
        {
            projectId,
            itemId,
            fieldId,
            optionId,
        },
    );
}

/**
 * Finds the matching project item for a known project id.
 *
 * @param {Array<object>} items
 * @param {string} projectId
 *
 * @returns {object|null}
 */
function findProjectItem(items, projectId) {
    return items.find((item) => item.project?.id === projectId) ?? null;
}

module.exports = {
    findProjectItem,
    getExistingFieldValue,
    getSingleSelectField,
    getSingleSelectOption,
    loadConfiguredProject,
    parseLinkedIssueNumber,
    updateSingleSelectField,
};
