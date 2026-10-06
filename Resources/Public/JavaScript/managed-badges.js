const settings = TYPO3.settings.schedulerAsCode ?? { tasks: {}, labels: {} };

function createBadge(state) {
  const badge = document.createElement('span');
  badge.classList.add('badge', state.orphaned ? 'badge-warning' : 'badge-info', 'ms-1');
  badge.dataset.schedulerAsCode = state.identifier;
  badge.textContent = state.orphaned ? settings.labels.orphaned : settings.labels.managed;
  badge.title = state.orphaned
    ? settings.labels.orphanedDescription.replace('%s', state.source)
    : state.source;
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
