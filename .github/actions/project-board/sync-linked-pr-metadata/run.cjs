const board = require('../shared/project-board-client.cjs');

/**
 * @param {{github: import('@actions/github/lib/utils').GitHub, context: any, core: any}} deps
 *
 * @returns {Promise<void>}
 */
module.exports = async function syncLinkedPrMetadata({ github, context, core }) {
    const pullRequest = context.payload.pull_request;
    const text = `${pullRequest.title}\n${pullRequest.body ?? ''}`;
    const issueNumber = board.parseLinkedIssueNumber(text);

    if (!issueNumber) {
        core.info('No linked issue reference found in the pull request title or body.');

        return;
    }

    const owner = context.repo.owner;
    const repo = context.repo.repo;
    const pullRequestNumber = pullRequest.number;

    const metadata = await github.graphql(
        `query($owner: String!, $repo: String!, $issueNumber: Int!, $pullRequestNumber: Int!) {
          repository(owner: $owner, name: $repo) {
            issue(number: $issueNumber) {
              id
              number
              milestone {
                number
                title
              }
              projectItems(first: 20) {
                nodes {
                  id
                  project {
                    ... on ProjectV2 {
                      id
                      title
                    }
                  }
                  fieldValues(first: 20) {
                    nodes {
                      __typename
                      ... on ProjectV2ItemFieldSingleSelectValue {
                        optionId
                        name
                        field {
                          ... on ProjectV2SingleSelectField {
                            id
                            name
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
            pullRequest(number: $pullRequestNumber) {
              projectItems(first: 20) {
                nodes {
                  id
                  project {
                    ... on ProjectV2 {
                      id
                      title
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
            owner,
            repo,
            issueNumber,
            pullRequestNumber,
        },
    );

    const issue = metadata.repository.issue;

    if (!issue) {
        core.info(`Linked issue #${issueNumber} was not found.`);

        return;
    }

    if (issue.milestone) {
        core.info(`Applying milestone "${issue.milestone.title}" to PR #${pullRequestNumber}.`);
        await github.request('PATCH /repos/{owner}/{repo}/issues/{issue_number}', {
            owner,
            repo,
            issue_number: pullRequestNumber,
            milestone: issue.milestone.number,
        });
    }

    const issueProjectItems = issue.projectItems.nodes;
    const pullRequestProjectItems = metadata.repository.pullRequest.projectItems.nodes;

    for (const issueProjectItem of issueProjectItems) {
        const project = issueProjectItem.project;

        if (!project) {
            continue;
        }

        const pullRequestProjectItem = pullRequestProjectItems.find((item) => item.project?.id === project.id) ?? null;

        if (!pullRequestProjectItem) {
            continue;
        }

        const pullRequestFieldValues = new Map(
            pullRequestProjectItem.fieldValues.nodes
                .filter((fieldValue) => 'ProjectV2ItemFieldSingleSelectValue' === fieldValue.__typename)
                .map((fieldValue) => [fieldValue.field?.name, fieldValue]),
        );

        for (const fieldName of ['Priority', 'Size']) {
            const issueValue = issueProjectItem.fieldValues.nodes.find(
                (fieldValue) => 'ProjectV2ItemFieldSingleSelectValue' === fieldValue.__typename && fieldValue.field?.name === fieldName,
            ) ?? null;
            const currentValue = pullRequestFieldValues.get(fieldName) ?? null;

            if (!issueValue || currentValue) {
                continue;
            }

            await board.updateSingleSelectField(
                github,
                project.id,
                pullRequestProjectItem.id,
                issueValue.field.id,
                issueValue.optionId,
            );
            core.info(`PR #${pullRequestNumber} inherited ${fieldName}="${issueValue.name}" from issue #${issue.number}.`);
        }
    }
};
