'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('quorumModal');
    const modal = modalElement && typeof bootstrap !== 'undefined'
        ? new bootstrap.Modal(modalElement)
        : null;
    const form = document.getElementById('quorumForm');
    const stepButtons = Array.from(document.querySelectorAll('[data-quorum-step]'));
    const sections = Array.from(document.querySelectorAll('[data-quorum-form-section]'));
    const previousButton = document.getElementById('btnPreviousQuorumStep');
    const nextButton = document.getElementById('btnNextQuorumStep');
    const submitButton = document.getElementById('btnSubmitQuorum');
    const rosterBuilder = document.getElementById('quorumRosterBuilder');
    const participants = window.VQDSS_QUORUM_PARTICIPANTS || [];
    const previews = [];
    let currentStep = 1;

    const showStep = (step) => {
        currentStep = step;
        stepButtons.forEach((button) => button.classList.toggle('active', Number(button.dataset.quorumStep) === step));
        sections.forEach((section) => section.classList.toggle('active', Number(section.dataset.quorumFormSection) === step));
        if (previousButton) previousButton.disabled = step === 1;
        nextButton?.classList.toggle('d-none', step === 4);
        submitButton?.classList.toggle('d-none', step !== 4);
    };

    const openQuorum = (data = {}) => {
        if (!modal || !form) return;
        form.reset();
        setText('quorumModalTitle', data.reference ? `Verify Quorum: ${data.reference}` : 'Create Quorum Verification');
        setValue('quorumSessionTitle', data.title || '');
        setValue('quorumSessionDate', dateKey(new Date()));
        setValue('quorumSessionTime', '09:00');
        const select = document.getElementById('quorumMeasureSelect');
        if (select) {
            select.value = data.id || '';
            select.dispatchEvent(new Event('change'));
        }
        resetRoster();
        calculate();
        showStep(1);
        modal.show();
    };

    document.getElementById('btnCreateQuorumCheck')?.addEventListener('click', () => openQuorum());
    document.getElementById('btnQuickQuorumCheck')?.addEventListener('click', () => openQuorum());
    document.querySelectorAll('[data-create-quorum]').forEach((button) => {
        button.addEventListener('click', () => openQuorum({
            id: button.dataset.createQuorum,
            reference: button.dataset.reference,
            title: button.dataset.title,
        }));
    });

    const requestedId = new URLSearchParams(window.location.search).get('verify');
    if (requestedId) {
        document.querySelector(`[data-create-quorum="${CSS.escape(requestedId)}"]`)?.click();
    }

    stepButtons.forEach((button) => button.addEventListener('click', () => showStep(Number(button.dataset.quorumStep))));
    previousButton?.addEventListener('click', () => currentStep > 1 && showStep(currentStep - 1));
    nextButton?.addEventListener('click', () => currentStep < 4 && showStep(currentStep + 1));

    const measureSelect = document.getElementById('quorumMeasureSelect');
    const measurePreview = document.getElementById('quorumMeasurePreview');
    measureSelect?.addEventListener('change', () => {
        const option = measureSelect.selectedOptions[0];
        if (!option?.value) {
            if (measurePreview) measurePreview.innerHTML = '<i class="bi bi-file-earmark-text"></i><div><strong>No legislative measure linked</strong><span>The quorum check may remain session-wide or be linked to a specific measure.</span></div>';
            return;
        }
        if (measurePreview) measurePreview.innerHTML = `<i class="bi bi-file-earmark-check"></i><div><strong>${escapeHtml(option.dataset.reference)}</strong><span>${escapeHtml(option.dataset.type)} · ${escapeHtml(option.dataset.status)} · ${escapeHtml(option.dataset.priority)}</span><small>${escapeHtml(option.dataset.title)}</small></div>`;
    });

    const calculate = () => {
        const official = Math.max(1, Number(document.getElementById('quorumOfficialMembers')?.value || 1));
        const rule = document.getElementById('quorumRule')?.value || 'majority';
        let required = Math.floor(official / 2) + 1;
        if (rule === 'two_thirds') required = Math.ceil(official * 2 / 3);
        if (rule === 'three_fourths') required = Math.ceil(official * 0.75);
        if (rule === 'fixed') required = Math.max(1, Number(document.getElementById('quorumFixedRequirement')?.value || 1));
        if (rule === 'custom') required = Math.ceil(official * Math.min(100, Math.max(1, Number(document.getElementById('quorumCustomPercentage')?.value || 50))) / 100);

        const remoteAllowed = document.getElementById('quorumRemoteCounts')?.value !== 'No';
        let present = 0;
        let eligible = 0;
        document.querySelectorAll('[data-quorum-roster-item]').forEach((row) => {
            const member = row.querySelector('[data-roster-member]')?.value;
            const attendance = row.querySelector('[data-roster-attendance]')?.value;
            const eligibility = row.querySelector('[data-roster-eligibility]')?.value;
            const counted = attendance === 'Present' || attendance === 'Late' || (attendance === 'Remote' && remoteAllowed);
            if (member && counted) {
                present++;
                if (eligibility === 'Eligible') eligible++;
            }
        });
        const met = present >= required;

        [['quorumOfficialCount', official], ['quorumRequiredCount', required], ['quorumPresentCount', present], ['quorumEligibleCount', eligible], ['quorumFinalOfficial', official], ['quorumFinalRequired', required], ['quorumFinalPresent', present], ['quorumFinalEligible', eligible]].forEach(([id, value]) => setText(id, value));
        setText('quorumPreliminaryResult', met ? 'Met' : 'Not Met');
        setText('quorumFinalResult', met ? 'Quorum Met' : 'Quorum Not Met');
        document.getElementById('quorumPreliminaryResult')?.classList.toggle('result-met', met);
        document.getElementById('quorumPreliminaryResult')?.classList.toggle('result-pending', !met);
        document.getElementById('quorumFinalResult')?.classList.toggle('is-met', met);
        document.getElementById('quorumFinalResult')?.classList.toggle('is-not-met', !met);
        return {official, required, present, eligible, met};
    };

    ['quorumOfficialMembers', 'quorumRule', 'quorumFixedRequirement', 'quorumCustomPercentage', 'quorumRemoteCounts'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', calculate);
        document.getElementById(id)?.addEventListener('change', calculate);
    });
    rosterBuilder?.addEventListener('change', calculate);

    const participantOptions = () => ['<option value="">-- Select member --</option>', ...participants.map((p) => `<option value="${escapeHtml(p.id)}">${escapeHtml(p.name)} — ${escapeHtml(p.role)}</option>`)].join('');

    document.getElementById('btnAddQuorumMember')?.addEventListener('click', () => {
        if (!rosterBuilder) return;
        const item = document.createElement('div');
        item.className = 'quorum-roster-item';
        item.dataset.quorumRosterItem = '';
        item.innerHTML = `<span class="roster-order">1</span><div class="roster-fields"><select class="form-select" name="member_id[]" data-roster-member>${participantOptions()}</select><select class="form-select" name="attendance_status[]" data-roster-attendance><option>Present</option><option>Remote</option><option>Absent</option><option>Excused</option><option>Late</option></select><select class="form-select" name="voting_eligibility[]" data-roster-eligibility><option>Eligible</option><option>Non-Voting</option><option>Disqualified</option><option>Conflict</option></select><input type="text" class="form-control" name="attendance_note[]" placeholder="Attendance or eligibility note"></div><button type="button" class="roster-remove-button" data-remove-quorum-member><i class="bi bi-trash"></i></button>`;
        rosterBuilder.appendChild(item);
        updateRosterOrder();
        calculate();
    });

    rosterBuilder?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-quorum-member]');
        if (!button) return;
        const rows = rosterBuilder.querySelectorAll('[data-quorum-roster-item]');
        if (rows.length <= 1) {
            dialog({icon:'info', title:'Roster Row Required', text:'Keep at least one roster row.'});
            return;
        }
        button.closest('[data-quorum-roster-item]')?.remove();
        updateRosterOrder();
        calculate();
    });

    document.getElementById('btnMarkAllPresent')?.addEventListener('click', () => {
        document.querySelectorAll('[data-roster-attendance]').forEach((select) => select.value = 'Present');
        calculate();
    });
    document.getElementById('btnImportQuorumRoster')?.addEventListener('click', () => dialog({icon:'info', title:'Committee Roster Import', text:'The final importer will load authorized committee membership records. Shared users can be added manually in this UI phase.'}));
    document.getElementById('btnSaveQuorumDraft')?.addEventListener('click', () => dialog({icon:'info', title:'Draft UI Ready', text:'Draft saving will be connected during backend implementation.'}));
    document.getElementById('btnRunRosterAudit')?.addEventListener('click', () => {
        const r = calculate();
        dialog({icon:r.met ? 'success' : 'warning', title:'Roster Audit Result', html:`<div class="text-start small"><p><strong>Official membership:</strong> ${r.official}</p><p><strong>Required:</strong> ${r.required}</p><p><strong>Present:</strong> ${r.present}</p><p><strong>Eligible present:</strong> ${r.eligible}</p><p><strong>Result:</strong> ${r.met ? 'Quorum Met' : 'Quorum Not Met'}</p></div>`});
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const title = document.getElementById('quorumSessionTitle')?.value.trim();
        const date = document.getElementById('quorumSessionDate')?.value;
        if (!title || !date) {
            dialog({icon:'warning', title:'Missing Session Information', text:'Enter a session title and date before certification.'});
            showStep(1);
            return;
        }
        const result = calculate();
        previews.unshift({title, date, time:document.getElementById('quorumSessionTime')?.value || '', venue:document.getElementById('quorumVenue')?.value || '', ...result});
        renderPreview();
        dialog({icon:result.met ? 'success' : 'warning', title:result.met ? 'Quorum Preview Certified' : 'Quorum Not Met', text:'The verification was added to the browser preview and is not yet stored in MySQL.'}).then(() => modal?.hide());
    });

    const renderPreview = () => {
        const container = document.getElementById('quorumSessionPreview');
        if (!container || !previews.length) return;
        container.innerHTML = previews.slice(0, 6).map((p) => `<div class="quorum-session-card"><div class="quorum-session-card-top"><span class="quorum-session-icon"><i class="bi bi-person-check"></i></span><div class="quorum-session-copy"><strong>${escapeHtml(p.title)}</strong><span>${escapeHtml(p.date)} · ${escapeHtml(p.time || 'Time pending')} · ${escapeHtml(p.venue || 'Venue pending')}</span></div><span class="quorum-session-result ${p.met ? 'met' : 'not-met'}">${p.met ? 'Quorum Met' : 'Not Met'}</span></div><div class="quorum-session-stats"><div><span>Official</span><strong>${p.official}</strong></div><div><span>Required</span><strong>${p.required}</strong></div><div><span>Present</span><strong>${p.present}</strong></div><div><span>Eligible</span><strong>${p.eligible}</strong></div></div><div class="quorum-session-meter"><span style="width:${Math.min(100, Math.round(p.present / p.official * 100))}%"></span></div></div>`).join('');
    };

    const checks = Array.from(document.querySelectorAll('.quorum-checklist-item'));
    checks.forEach((item) => item.addEventListener('change', () => {
        const percent = Math.round(checks.filter((check) => check.checked).length / checks.length * 100);
        setText('quorumChecklistValue', `${percent}%`);
        const bar = document.getElementById('quorumChecklistBar');
        if (bar) bar.style.width = `${percent}%`;
    }));

    const fileInput = document.getElementById('quorumDocuments');
    const fileList = document.getElementById('quorumSelectedFiles');
    fileInput?.addEventListener('change', () => {
        if (!fileList) return;
        fileList.innerHTML = Array.from(fileInput.files).map((file) => `<div class="quorum-file-item"><i class="bi bi-file-earmark"></i><span>${escapeHtml(file.name)}</span><small>${formatFileSize(file.size)}</small></div>`).join('');
    });

    document.querySelectorAll('[data-quorum-history]').forEach((button) => button.addEventListener('click', () => dialog({icon:'info', title:'Quorum Verification History', text:'Session creation, attendance changes, rechecks, certifications, and voting handoff history will be connected later.'})));

    const search = document.getElementById('quorumSearch');
    const type = document.getElementById('quorumTypeFilter');
    const priority = document.getElementById('quorumPriorityFilter');
    const status = document.getElementById('quorumStatusFilter');
    const rows = Array.from(document.querySelectorAll('.quorum-data-row'));
    const noResult = document.getElementById('quorumNoFilterResult');
    const count = document.getElementById('quorumRecordCount');

    const applyFilters = () => {
        const query = (search?.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const show = (!query || (row.dataset.search || '').includes(query)) && (!type?.value || row.dataset.type === type.value) && (!priority?.value || row.dataset.priority === priority.value) && (!status?.value || row.dataset.status === status.value);
            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });
        noResult?.classList.toggle('d-none', visible > 0 || rows.length === 0);
        if (count) count.textContent = `Showing ${visible} candidate record(s)`;
    };
    [search, type, priority, status].forEach((element) => {
        element?.addEventListener('input', applyFilters);
        element?.addEventListener('change', applyFilters);
    });
    document.getElementById('btnResetQuorumFilters')?.addEventListener('click', () => {
        if (search) search.value = '';
        if (type) type.value = '';
        if (priority) priority.value = '';
        if (status) status.value = '';
        applyFilters();
    });

    function updateRosterOrder() {
        rosterBuilder?.querySelectorAll('[data-quorum-roster-item]').forEach((row, index) => {
            const order = row.querySelector('.roster-order');
            if (order) order.textContent = String(index + 1);
        });
    }
    function resetRoster() {
        if (!rosterBuilder) return;
        const rows = Array.from(rosterBuilder.querySelectorAll('[data-quorum-roster-item]'));
        rows.slice(1).forEach((row) => row.remove());
        rows[0]?.querySelectorAll('input').forEach((input) => input.value = '');
        rows[0]?.querySelectorAll('select').forEach((select) => select.selectedIndex = 0);
        updateRosterOrder();
    }
    function setValue(id, value) { const element = document.getElementById(id); if (element) element.value = String(value ?? ''); }
    function setText(id, value) { const element = document.getElementById(id); if (element) element.textContent = String(value ?? ''); }
    function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`; }
    function escapeHtml(value) { const element = document.createElement('div'); element.textContent = String(value ?? ''); return element.innerHTML; }
    function formatFileSize(bytes) { return bytes < 1024 ? `${bytes} B` : bytes < 1048576 ? `${(bytes/1024).toFixed(1)} KB` : `${(bytes/1048576).toFixed(1)} MB`; }
    function dialog(options) {
        if (typeof Swal !== 'undefined') return Swal.fire(options);
        const div = document.createElement('div'); div.innerHTML = options.html || '';
        window.alert(`${options.title || 'Notice'}\n\n${options.text || div.textContent || ''}`);
        return Promise.resolve();
    }

    calculate();
});
