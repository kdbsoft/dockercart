/** @type {import('semantic-release').GlobalConfig} */
module.exports = {
  branches: ['main'],
  tagFormat: 'v${version}',
  plugins: [
    [
      '@semantic-release/commit-analyzer',
      {
        preset: 'conventionalcommits',
        releaseRules: [
          { type: 'feat', release: 'minor' },
          { type: 'fix', release: 'patch' },
          { type: 'perf', release: 'patch' },
          { type: 'refactor', release: 'patch' }
        ]
      }
    ],
    [
      '@semantic-release/release-notes-generator',
      {
        preset: 'conventionalcommits'
      }
    ],
    [
      '@semantic-release/changelog',
      {
        changelogFile: 'CHANGELOG.md'
      }
    ],
    [
      '@semantic-release/exec',
      {
        prepareCmd:
          "npm version ${nextRelease.version} --no-git-tag-version && printf '%s\\n' ${nextRelease.version} > VERSION"
      }
    ],
    [
      '@semantic-release/git',
      {
        assets: ['CHANGELOG.md', 'VERSION', 'package.json', 'package-lock.json'],
        message:
          'chore(release): ${nextRelease.version} [skip ci]\\n\\n${nextRelease.notes}'
      }
    ],
    [
      '@semantic-release/github',
      {
        successComment: false,
        failComment: false
      }
    ]
  ]
};
