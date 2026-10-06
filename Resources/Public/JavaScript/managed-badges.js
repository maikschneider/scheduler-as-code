const settings = TYPO3.settings.schedulerAsCode ?? { tasks: {}, labels: {} };

// Tasks linked before the source was recorded only have their identifier, and back then
// every task file lived in config/scheduler/.
const sourceOf = (state) => state.source || 'config/scheduler/' + state.identifier + '.yaml';

function createBadge(state) {
  const badge = document.createElement('span');
  badge.classList.add('badge', state.orphaned ? 'badge-warning' : 'badge-info', 'ms-1');
  badge.dataset.schedulerAsCode = state.identifier;
  badge.textContent = state.orphaned ? settings.labels.orphaned : settings.labels.managed;
  badge.title = state.orphaned
    ? settings.labels.orphanedDescription.replace('%s', sourceOf(state))
    : sourceOf(state);
  return badge;
}

function decorate() {
  document.querySelectorAll('tr[data-task-id]').forEach((row) => {
    const state = settings.tasks[row.dataset.taskId];
    const title = row.querySelector('strong');
    if (!state || !title || row.querySelector('[data-scheduler-as-code]')) {
      return;
    }
    title.after(createBadge(state));
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', decorate);
} else {
  decorate();
}
