/**
 * @param {{github: import('@actions/github/lib/utils').GitHub, context: any, core: any}} deps
 *
 * @returns {Promise<void>}
 */
module.exports = async function resolveReviewStatus({ github, context, core }) {
    const result = await github.graphql(
        `query($owner: String!, $repo: String!, $pullRequestNumber: Int!) {
          repository(owner: $owner, name: $repo) {
            pullRequest(number: $pullRequestNumber) {
              isDraft
              reviewDecision
            }
          }
        }`,
        {
            owner: context.repo.owner,
            repo: context.repo.repo,
            pullRequestNumber: context.payload.pull_request.number,
        },
    );

    const pullRequest = result.repository.pullRequest;

    if (pullRequest.isDraft) {
        core.setOutput('status', 'In progress');

        return;
    }

    if ('APPROVED' === pullRequest.reviewDecision) {
        core.setOutput('status', 'Ready to Merge');

        return;
    }

    core.setOutput('status', 'In review');
};
