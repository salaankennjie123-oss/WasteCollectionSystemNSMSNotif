document.addEventListener('DOMContentLoaded', () => {
  const listView = document.getElementById('list-view');
  const calendarView = document.getElementById('calendar-view');

  function setView(view) {
    document.querySelectorAll('.toggle-btn').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.view === view);
    });

    if (view === 'calendar') {
      listView?.classList.add('hidden');
      calendarView?.classList.remove('hidden');
    } else {
      calendarView?.classList.add('hidden');
      listView?.classList.remove('hidden');
    }

    try { sessionStorage.setItem('scheduleView', view); } catch (e) { /* ignore */ }
  }

  document.querySelectorAll('.toggle-btn').forEach((button) => {
    button.addEventListener('click', () => setView(button.dataset.view));
  });

  // Remember the chosen view (the public page auto-refreshes)
  try {
    const saved = sessionStorage.getItem('scheduleView');
    if (saved && document.querySelector('.toggle-btn')) setView(saved);
  } catch (e) { /* ignore */ }

  document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.openModal;
      const target = document.getElementById(id);
      if (target) target.classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const id = button.dataset.closeModal;
      const target = document.getElementById(id);
      if (target) target.classList.add('hidden');
    });
  });

  function formatMobileInput(value) {
    const digits = (value || '').replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 4) return digits;
    if (digits.length <= 7) return `${digits.slice(0, 4)} ${digits.slice(4)}`;
    return `${digits.slice(0, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
  }

  const residentMobile = document.getElementById('resident_mobile');
  if (residentMobile) {
    residentMobile.addEventListener('input', function () {
      this.value = formatMobileInput(this.value);
    });
  }

  const residentForm = document.getElementById('resident-form');
  if (residentForm) {
    residentForm.addEventListener('submit', function (e) {
      const name = document.getElementById('resident_name')?.value.trim() || '';
      const mobile = document.getElementById('resident_mobile')?.value.trim() || '';
      const sitio = document.getElementById('resident_sitio')?.value.trim() || '';

      const validName = name !== '';
      const validMobile = /^09\d{2} \d{3} \d{4}$/.test(mobile);
      const validSitio = sitio !== '';

      if (!validName || !validMobile || !validSitio) {
        e.preventDefault();
        alert('Please enter a valid resident name, mobile number in 09XX XXX XXXX format, and sitio.');
      }
    });
  }

  document.querySelectorAll('[data-edit-resident]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = JSON.parse(button.dataset.editResident);
      const modal = document.getElementById('resident-modal');

      document.getElementById('resident_id').value = row.resident_id || '';
      document.getElementById('resident_name').value = row.name || '';
      document.getElementById('resident_mobile').value = formatMobileInput(row.mobile_number || '');
      document.getElementById('resident_sitio').value = row.sitio || '';

      document.getElementById('resident-modal-title').textContent = row.resident_id ? 'Edit Resident' : 'Add Resident';
      modal?.classList.remove('hidden');
    });
  });

  document.querySelectorAll('[data-edit-announcement]').forEach((button) => {
    button.addEventListener('click', () => {
      const row = JSON.parse(button.dataset.editAnnouncement);
      const modal = document.getElementById('announcement-modal');

      document.getElementById('announcement_id').value = row.announcement_id || '';
      document.getElementById('announcement_title').value = row.title || '';
      document.getElementById('announcement_body').value = row.body || '';
      document.getElementById('announcement_status').value = row.status || 'Draft';

      document.getElementById('announcement-modal-title').textContent = row.announcement_id ? 'Edit Announcement' : 'New Announcement';
      modal?.classList.remove('hidden');
    });
  });

  // Auto-refresh only the live schedule page (never login or admin forms)
  if (document.body.classList.contains('live-page')) {
    setInterval(() => {
      location.reload();
    }, 10000);
  }

  // Login lockout countdown
  const lockTimer = document.getElementById('lock-timer');
  if (lockTimer) {
    let left = parseInt(lockTimer.dataset.lockSeconds, 10) || 0;
    const fmt = (n) => (n >= 60 ? `${Math.floor(n / 60)} min ${n % 60} sec` : `${n} sec`);

    const tick = setInterval(() => {
      left -= 1;

      if (left <= 0) {
        clearInterval(tick);
        // Lock has expired: reload to a fresh, unlocked form
        window.location.href = 'login.php';
        return;
      }

      lockTimer.textContent = fmt(left);
    }, 1000);
  }
});