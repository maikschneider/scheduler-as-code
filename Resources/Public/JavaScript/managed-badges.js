const settings = TYPO3.settings.schedulerAsCode ?? { tasks: {}, labels: {} };

// Tasks linked before the source was recorded only have their identifier, and back then
// every task file lived in config/scheduler/.
const sourceOf = (state) => state.source || 'config/scheduler/' + state.identifier + '.yaml';

function createBadge(state) {
  const badge = document.createElement('span');
  badge.classList.add('badge', 'ms-1');
  badge.dataset.schedulerAsCode = state.identifier;
  if (state.orphaned) {
    badge.classList.add('badge-warning');
    badge.textContent = settings.labels.orphaned;
    badge.title = settings.labels.orphanedDescription.replace('%s', sourceOf(state));
  } else if (state.stale) {
    badge.classList.add('badge-notice');
    badge.textContent = settings.labels.stale;
    badge.title = settings.labels.staleDescription.replace('%s', sourceOf(state));
  } else {
    badge.classList.add('badge-info');
    badge.textContent = settings.labels.managed;
    badge.title = sourceOf(state);
  }
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
