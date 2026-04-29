const board = require('../shared/project-board-client.cjs');

/**
 * @param {{github: import('@actions/github/lib/utils').GitHub, context: any, core: any}} deps
 *
 * @returns {Promise<void>}
 */
module.exports = async function resolvePrStatus({ github, context, core }) {
    const pullRequest = context.payload.pull_request;
    const text = `${pullRequest.title}\n${pullRequest.body ?? ''}`;
    const issueNumber = board.parseLinkedIssueNumber(text);

    let pullRequestStatus = 'In review';
    let linkedIssueStatus = '';
    let linkedIssueNodeId = '';

    if ('closed' === context.payload.action && pullRequest.merged !== true) {
        pullRequestStatus = 'Backlog';
        linkedIssueStatus = 'Backlog';
    } else if (pullRequest.merged === true) {
        pullRequestStatus = 'Merged';
        linkedIssueStatus = 'Merged';
    } else if (pullRequest.draft) {
        pullRequestStatus = 'In progress';
        linkedIssueStatus = issueNumber ? 'In progress' : '';
    } else {
        linkedIssueStatus = issueNumber ? 'In progress' : '';
    }

    if (issueNumber) {
        const issue = await github.graphql(
            `query($owner: String!, $repo: String!, $issueNumber: Int!) {
              repository(owner: $owner, name: $repo) {
                issue(number: $issueNumber) {
                  id
                }
              }
            }`,
            {
                owner: context.repo.owner,
                repo: context.repo.repo,
                issueNumber,
            },
        );

        linkedIssueNodeId = issue.repository.issue?.id ?? '';
    }

    core.setOutput('pull-request-status', pullRequestStatus);
    core.setOutput('linked-issue-node-id', linkedIssueNodeId);
    core.setOutput('linked-issue-status', linkedIssueStatus);
};
