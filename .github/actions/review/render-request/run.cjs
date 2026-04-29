const MAX_LISTED_FILES = 12;

function classifyTouchedSurfaces(files) {
  const checks = [
    [
      'Source behavior or orchestration changed; verify regressions, contracts, and dependency boundaries.',
      (file) => file.startsWith('src/'),
    ],
    [
      'Tests changed; confirm assertions still cover the touched behavior and edge cases.',
      (file) => file.startsWith('tests/'),
    ],
    [
      'Docs or README changed; verify commands, examples, and generated outputs stayed aligned.',
      (file) => file === 'README.md' || file.startsWith('docs/'),
    ],
    [
      'Workflow or local GitHub Action logic changed; require executable validation of permissions, triggers, bot-authored commits, and CI side effects.',
      (file) => file.startsWith('.github/workflows/') || file.startsWith('.github/actions/'),
    ],
    [
      'Consumer workflow wrappers changed; verify permissions, inputs, and reusable workflow refs remain aligned with the packaged contract.',
      (file) => file.startsWith('resources/github-actions/'),
    ],
    [
      'Packaged agent surfaces changed; verify prompts, sync behavior, and inherited guidance.',
      (file) => file.startsWith('.agents/skills/') || file.startsWith('.agents/agents/') || file === 'AGENTS.md',
    ],
    [
      'Changelog changed; verify notable behavior and automation changes are documented accurately.',
      (file) => file === 'CHANGELOG.md',
    ],
    [
      'Wiki output changed; confirm generated content updates are intentional and consistent with the source.',
      (file) => file.startsWith('.github/wiki'),
    ],
    [
      'Packaged resources changed; verify consumer repositories inherit the right defaults and generated artifacts.',
      (file) => file.startsWith('resources/'),
    ],
  ];

  return checks
    .filter(([, matcher]) => files.some(matcher))
    .map(([message]) => message);
}

function renderComment({pull, files, focusAreas}) {
  const listedFiles = files.slice(0, MAX_LISTED_FILES);
  const remainingCount = Math.max(0, files.length - listedFiles.length);
  const touchedSurfaces = focusAreas.length > 0
    ? focusAreas
    : ['No special review surface was inferred beyond the normal findings-first review contract.'];

  const lines = [
    '## Rigorous review requested',
    '',
    'This pull request is ready for the dedicated `review-guardian` pass powered by `$pull-request-review`.',
    '',
    `- PR: #${pull.number} — ${pull.title}`,
    `- Author: @${pull.user.login}`,
    `- Base: \`${pull.base.ref}\``,
    `- Head: \`${pull.head.ref}\` @ \`${pull.head.sha.slice(0, 7)}\``,
    '',
    '### Review focus',
    ...touchedSurfaces.map((item) => `- ${item}`),
    '',
    '### Sample changed files',
    ...listedFiles.map((file) => `- \`${file}\``),
  ];

  if (remainingCount > 0) {
    lines.push(`- ...and ${remainingCount} more files.`);
  }

  lines.push(
    '',
    '### Suggested prompt',
    '```text',
    `Use $pull-request-review with the review-guardian agent to review PR #${pull.number} (${pull.html_url}).`,
    'Lead with findings ordered by severity. Include repository file references whenever possible.',
    'Prioritize bugs, regressions, missing tests, missing docs, generated-output drift, and workflow or CI impacts.',
    'For workflow/action changes, record validation evidence or residual risk; pay special attention to GITHUB_TOKEN pushes and required-check dispatch/mirroring.',
    `Base: ${pull.base.ref}`,
    `Head: ${pull.head.ref} @ ${pull.head.sha.slice(0, 7)}`,
    '```',
  );

  return lines.join('\n');
}

function renderSummary({pull, focusAreas}) {
  const lines = [
    '## Rigorous review requested',
    '',
    `- Pull request: [#${pull.number}](${pull.html_url})`,
    '- Agent: `review-guardian`',
    '- Skill: `$pull-request-review`',
    '',
    '### Findings-first expectations',
    '- Lead with bugs, regressions, missing tests, missing docs, generated-output drift, and workflow or CI risk.',
    '- Include repository file references whenever possible.',
    '- For workflow/action changes, record executable validation evidence or residual risk, especially around bot-authored commits and required checks.',
  ];

  if (focusAreas.length > 0) {
    lines.push('', '### Inferred high-signal surfaces', ...focusAreas.map((item) => `- ${item}`));
  }

  return lines.join('\n');
}

module.exports = async function run({github, context, core}) {
  const owner = context.repo.owner;
  const repo = context.repo.repo;
  const input = core.getInput('pull-request-number').trim();
  const inferredNumber = String(context.payload.pull_request?.number || '').trim();
  const pullRequestNumber = input || inferredNumber;

  if (pullRequestNumber === '') {
    core.setFailed('Unable to resolve a pull request number for the rigorous review workflow.');
    return;
  }

  const pull_number = Number.parseInt(pullRequestNumber, 10);

  if (Number.isNaN(pull_number)) {
    core.setFailed(`Invalid pull request number: ${pullRequestNumber}`);
    return;
  }

  const {data: pull} = await github.rest.pulls.get({
    owner,
    repo,
    pull_number,
  });

  const files = await github.paginate(github.rest.pulls.listFiles, {
    owner,
    repo,
    pull_number,
    per_page: 100,
  });

  const changedFiles = files.map((file) => file.filename);
  const focusAreas = classifyTouchedSurfaces(changedFiles);

  core.setOutput('pull-request-number', String(pull.number));
  core.setOutput('comment', renderComment({pull, files: changedFiles, focusAreas}));
  core.setOutput('summary', renderSummary({pull, focusAreas}));
};
