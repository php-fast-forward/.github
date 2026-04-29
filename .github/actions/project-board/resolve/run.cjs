/**
 * @param {{github: import('@actions/github/lib/utils').GitHub, context: any, core: any}} deps
 *
 * @returns {Promise<void>}
 */
module.exports = async function resolveProject({ github, context, core }) {
    const configuredProjectNumber = (process.env.INPUT_PROJECT ?? '').trim();

    if (configuredProjectNumber) {
        core.setOutput('project-number', configuredProjectNumber);

        return;
    }

    if ('php-fast-forward' !== context.repo.owner) {
        core.info('No project number was provided. Consumer repositories SHOULD pass project or configure PROJECT in their wrapper workflow.');
        core.setOutput('project-number', '');

        return;
    }

    const result = await github.graphql(
        `query($owner: String!) {
          organization(login: $owner) {
            projectsV2(first: 1, orderBy: {field: TITLE, direction: ASC}) {
              nodes {
                number
                title
              }
            }
          }
        }`,
        {
            owner: context.repo.owner,
        },
    );

    const project = result.organization?.projectsV2?.nodes?.[0] ?? null;

    if (!project) {
        core.info(`No GitHub Project V2 was found for ${context.repo.owner}.`);
        core.setOutput('project-number', '');

        return;
    }

    core.info(`Defaulting to organization project #${project.number} (${project.title}).`);
    core.setOutput('project-number', String(project.number));
};
