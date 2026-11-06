document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', event => {
    if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) event.preventDefault();
  });
});
document.querySelectorAll('[data-filter-table]').forEach(input => {
  input.addEventListener('input', () => {
    const table = document.querySelector(input.dataset.filterTable);
    if (!table) return;
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.hidden = !row.innerText.toLowerCase().includes(q);
    });
  });
});

if (window.labtraceChart && document.getElementById('categoryChart')) {
  const ctx = document.getElementById('categoryChart');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: window.labtraceChart.labels,
      datasets: [{ label: 'Assets', data: window.labtraceChart.values, borderRadius: 0, backgroundColor: '#60788d', maxBarThickness: 42 }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eff1f5' } }, x: { grid: { display: false } } }
    }
  });
}
