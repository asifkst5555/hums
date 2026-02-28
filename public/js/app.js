let currentUser = null;
let loginRole = 'admin';

const BEN_COL_STORAGE_KEY = 'hums_ben_visible_columns_v1';
const BEN_COLUMN_DEFS = [
  { key: 'misNumber', label: 'MIS নম্বর' },
  { key: 'name', label: 'নাম' },
  { key: 'nameEn', label: 'নাম (ইংরেজি)' },
  { key: 'gender', label: 'লিঙ্গ' },
  { key: 'nid', label: 'NID নম্বর' },
  { key: 'program', label: 'কার্যক্রম' },
  { key: 'union', label: 'ইউনিয়ন' },
  { key: 'phone', label: 'ফোন' },
  { key: 'dob', label: 'জন্ম তারিখ' },
  { key: 'father', label: 'পিতার নাম (বাংলা)' },
  { key: 'fatherEn', label: 'পিতার নাম (ইংরেজি)' },
  { key: 'mother', label: 'মাতার নাম (বাংলা)' },
  { key: 'motherEn', label: 'মাতার নাম (ইংরেজি)' },
  { key: 'spouseNameBn', label: 'স্বামী/স্ত্রীর নাম (বাংলা)' },
  { key: 'spouseNameEn', label: 'স্বামী/স্ত্রীর নাম (ইংরেজি)' },
  { key: 'bankMfs', label: 'ব্যাংক/এমএফএস' },
  { key: 'accountNumber', label: 'অ্যাকাউন্ট নম্বর' },
  { key: 'age', label: 'বয়স' },
  { key: 'division', label: 'বিভাগ' },
  { key: 'district', label: 'জেলা' },
  { key: 'upazila', label: 'উপজেলা' },
  { key: 'ward', label: 'ওয়ার্ড' },
  { key: 'addr', label: 'ঠিকানা' },
  { key: 'status', label: 'অবস্থা' },
];
const DEFAULT_VISIBLE_BEN_COLUMNS = ['name', 'nid', 'program', 'union', 'phone', 'status'];

function getVisibleBenColumns() {
  let parsed = null;
  try {
    parsed = JSON.parse(localStorage.getItem(BEN_COL_STORAGE_KEY) || '[]');
  } catch {
    parsed = [];
  }
  const keys = new Set(BEN_COLUMN_DEFS.map((c) => c.key));
  const valid = Array.isArray(parsed) ? parsed.filter((k) => keys.has(k)) : [];
  if (valid.length) return valid;
  return [...DEFAULT_VISIBLE_BEN_COLUMNS];
}

function saveVisibleBenColumns(columns) {
  localStorage.setItem(BEN_COL_STORAGE_KEY, JSON.stringify(columns));
}

var appBaseCache = null;
function getAppBase() {
  if (appBaseCache !== null) return appBaseCache;
  const marker = '/public/';
  const path = window.location.pathname || '';
  const idx = path.indexOf(marker);
  appBaseCache = idx >= 0 ? path.slice(0, idx) : '';
  return appBaseCache;
}

function withBase(path) {
  if (/^https?:\/\//i.test(path)) return path;
  return `${getAppBase()}${path}`;
}

const state = {
  beneficiaries: [],
  beneficiariesMeta: { total: 0, page: 1, pageSize: 50, totalPages: 1 },
  benVisibleColumns: getVisibleBenColumns(),
  beneficiaryPrograms: [],
  officerProfile: null,
  institutionTypes: [],
  institutions: [],
  users: [],
  unions: [],
  duplicateGroups: [],
  duplicateCount: 0,
  dashboardReport: null,
};

let editingId = { ben: null, inst: null, user: null, program: null };
let filteredInst = [];
let benSearchDebounce = null;
const benQuery = { page: 1, pageSize: 50, q: '', program: '' };

function esc(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function showInlineLoginMessage(message, type = 'error') {
  const box = document.getElementById('loginInlineMsg');
  if (!box) return;
  if (!message) {
    box.className = 'alert inline-msg';
    box.textContent = '';
    return;
  }
  box.className = `alert inline-msg show ${type === 'success' ? 'success' : 'error'}`;
  box.textContent = message;
}

function showToast(message, type = 'error', timeoutMs = 3200) {
  const wrap = document.getElementById('toastWrap');
  if (!wrap || !message) return;
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  wrap.appendChild(toast);
  setTimeout(() => {
    toast.remove();
  }, timeoutMs);
}

function notify(message, type = 'error', opts = {}) {
  if (opts.loginInline) {
    showInlineLoginMessage(message, type);
    return;
  }
  showToast(message, type, opts.timeoutMs || 3200);
}

function toBnDigits(input) {
  const map = { 0: '০', 1: '১', 2: '২', 3: '৩', 4: '৪', 5: '৫', 6: '৬', 7: '৭', 8: '৮', 9: '৯' };
  return String(input).replace(/\d/g, (d) => map[d] || d);
}

function updateTopDateTime() {
  const el = document.getElementById('topDateTime');
  if (!el) return;

  const now = new Date();
  const bnMonths = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
  const day = toBnDigits(now.getDate());
  const month = bnMonths[now.getMonth()];
  const year = toBnDigits(now.getFullYear());

  let hour = now.getHours();
  const ampm = hour >= 12 ? 'PM' : 'AM';
  hour = hour % 12 || 12;
  const minute = String(now.getMinutes()).padStart(2, '0');

  const hourBn = toBnDigits(String(hour).padStart(2, '0'));
  const minuteBn = toBnDigits(minute);
  el.textContent = `${day} ${month}, ${year} — ${hourBn}:${minuteBn} ${ampm}`;
}

async function api(path, options = {}) {
  const res = await fetch(withBase(path), {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });

  if (res.status === 204) return null;

  let data = null;
  try {
    data = await res.json();
  } catch {
    data = null;
  }

  if (!res.ok) {
    const error = new Error(data?.error || 'Request failed');
    error.status = res.status;
    throw error;
  }

  return data;
}

function switchRole(r) {
  loginRole = r;
  showInlineLoginMessage('');
  document.querySelectorAll('.login-tab').forEach((t, i) =>
    t.classList.toggle('active', (r === 'admin' && i === 0) || (r === 'viewer' && i === 1))
  );

  if (r === 'admin') {
    document.getElementById('username').value = 'admin';
  } else {
    document.getElementById('username').value = 'viewer';
  }
  document.getElementById('password').value = '';
}

async function doLogin() {
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  showInlineLoginMessage('');

  try {
    const result = await api('/api/auth/login.php', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    });

    currentUser = {
      id: result.user.id,
      name: result.user.name,
      role: result.user.role,
      username: result.user.username,
    };

    afterLogin();
  } catch (err) {
    notify(err.message || 'Login failed', 'error', { loginInline: true });
  }
}

async function doLogout() {
  try {
    await api('/api/auth/logout.php', { method: 'POST' });
  } catch {
    // no-op
  }

  currentUser = null;
  document.getElementById('loginScreen').style.display = 'grid';
  document.getElementById('app').style.display = 'none';
}

function applyRoleVisibility() {
  const isAdmin = currentUser && currentUser.role === 'admin';
  const isOperator = currentUser && currentUser.role === 'operator';
  const canEdit = isAdmin || isOperator;

  document.querySelectorAll('.admin-only').forEach((el) => {
    el.style.display = canEdit ? '' : 'none';
  });
  document.querySelectorAll('.admin-strict').forEach((el) => {
    el.style.display = isAdmin ? '' : 'none';
  });
  document.getElementById('adminNav').style.display = isAdmin ? '' : 'none';
}

function showPage(name) {
  document.querySelectorAll('.page').forEach((p) => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));
  document.getElementById('page-' + name).classList.add('active');
  document.querySelectorAll('.nav-item').forEach((n) => {
    if (n.getAttribute('onclick') && n.getAttribute('onclick').includes(name)) n.classList.add('active');
  });
  if (name === 'duplicate') {
    loadDuplicateList();
  }
}

async function loadBeneficiaries() {
  const params = new URLSearchParams({
    page: String(benQuery.page),
    pageSize: String(benQuery.pageSize),
  });
  if (benQuery.q) params.set('q', benQuery.q);
  if (benQuery.program) params.set('program', benQuery.program);

  const res = await api(`/api/beneficiaries/index.php?${params.toString()}`);
  state.beneficiaries = Array.isArray(res.items) ? res.items : [];
  state.beneficiariesMeta = {
    total: Number(res.total || 0),
    page: Number(res.page || 1),
    pageSize: Number(res.pageSize || benQuery.pageSize),
    totalPages: Number(res.totalPages || 1),
  };
  benQuery.page = state.beneficiariesMeta.page;
}

async function loadDuplicateList() {
  const dups = await api('/api/duplicate/list.php');
  state.duplicateGroups = dups.duplicates || [];
  state.duplicateCount = state.duplicateGroups.length;
  renderDupList();
  updateStats();
}

async function loadData() {
  const [insts, unions, dupCount, users, report, programs, instTypes, officer] = await Promise.allSettled([
    api('/api/institutions/index.php'),
    api('/api/unions/index.php'),
    api('/api/duplicate/count.php'),
    currentUser.role === 'admin' ? api('/api/users/index.php') : Promise.resolve([]),
    api('/api/report/overview.php'),
    api('/api/beneficiaries/programs.php'),
    api('/api/institutions/types.php'),
    api('/api/officer/profile.php'),
  ]);

  state.institutions = insts.status === 'fulfilled' ? insts.value : [];
  state.unions = unions.status === 'fulfilled' ? unions.value : [];
  state.duplicateCount = dupCount.status === 'fulfilled' ? Number(dupCount.value.count || 0) : 0;
  state.duplicateGroups = [];
  state.users = users.status === 'fulfilled' ? users.value : [];
  state.dashboardReport = report.status === 'fulfilled' ? report.value : null;
  state.beneficiaryPrograms = programs.status === 'fulfilled' ? programs.value : [];
  state.institutionTypes = instTypes.status === 'fulfilled' ? instTypes.value : [];
  state.officerProfile = officer.status === 'fulfilled' ? officer.value : null;
  filteredInst = [...state.institutions];
  renderBeneficiaryProgramFilter();
  renderInstitutionTypeOptions();
  await loadBeneficiaries();
}

async function refreshAll() {
  await loadData();
  renderBen();
  renderBenColumnOptions();
  renderInst();
  renderUsers();
  renderUnions();
  updateStats();
  renderRecent();
  renderAdvancedReport();
  renderOfficerProfile();
  renderProgramsPanel();
  document.getElementById('dupList').innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">দ্বৈত তালিকা দেখতে এই পেইজে প্রবেশ করুন</td></tr>';
}

function renderOfficerProfile() {
  const p = state.officerProfile;
  if (!p) return;
  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '-';
  };
  setText('officerName', p.name);
  setText('officerDesignation', p.designation);
  setText('officerDesignationTag', p.designation);
  setText('officerJoinDate', p.joinDate || '-');
  setText('officerTelephone', p.telephone || '-');
  setText('officerMobile', p.mobile || '-');
  setText('officerEmail', p.email || '-');

  const img = document.getElementById('officerPhoto');
  if (img && p.photoPath) {
    const stamp = encodeURIComponent(String(p.updatedAt || Date.now()));
    img.src = `${p.photoPath}?t=${stamp}`;
  }
}

function benStatusLabel(value) {
  if (value === 'active') return 'সক্রিয়';
  if (value === 'inactive') return 'নিষ্ক্রিয়';
  return 'অপেক্ষমাণ';
}

function formatBenCell(columnKey, b) {
  const value = b?.[columnKey];
  if (columnKey === 'name') return `<strong>${esc(value || '-')}</strong>`;
  if (columnKey === 'nid') return `<span style="font-family:monospace;font-size:12px">${esc(value || '-')}</span>`;
  if (columnKey === 'program') return `<span class="status-badge" style="background:#e8f0fa;color:#2c5aa0">${esc(value || '-')}</span>`;
  if (columnKey === 'status') return `<span class="status-badge status-${esc(value || 'pending')}">${benStatusLabel(value)}</span>`;
  if (columnKey === 'dob') return esc(value || '-');
  if (columnKey === 'age') return esc(value ?? '-');
  return esc(value || '-');
}

function renderBen(data = state.beneficiaries) {
  const tbody = document.getElementById('benBody');
  const theadRow = document.getElementById('benHead');
  const visibleCols = state.benVisibleColumns.length ? state.benVisibleColumns : [...DEFAULT_VISIBLE_BEN_COLUMNS];
  const selectedDefs = BEN_COLUMN_DEFS.filter((c) => visibleCols.includes(c.key));

  theadRow.innerHTML = `<th>#</th>${selectedDefs.map((c) => `<th>${esc(c.label)}</th>`).join('')}<th>কার্যক্রম</th>`;

  const colspan = selectedDefs.length + 2;
  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="${colspan}"><div class="empty-state"><div class="icon">👥</div>কোনো রেকর্ড নেই</div></td></tr>`;
  } else {
    const canEdit = currentUser && (currentUser.role === 'admin' || currentUser.role === 'operator');
    const startIndex = (state.beneficiariesMeta.page - 1) * state.beneficiariesMeta.pageSize;
    tbody.innerHTML = data
      .map(
        (b, i) => `
      <tr>
        <td>${startIndex + i + 1}</td>
        ${selectedDefs.map((c) => `<td>${formatBenCell(c.key, b)}</td>`).join('')}
        <td><div class="table-actions">${canEdit ? `<button class="btn btn-icon btn-edit" title="সম্পাদনা" aria-label="সম্পাদনা" onclick="editBen(${b.id})">✎</button><button class="btn btn-icon btn-delete" title="মুছুন" aria-label="মুছুন" onclick="deleteBen(${b.id})">🗑</button>` : ''}</div></td>
      </tr>`
      )
      .join('');
  }

  const info = document.getElementById('benPageInfo');
  const prev = document.getElementById('benPrevBtn');
  const next = document.getElementById('benNextBtn');
  const nums = document.getElementById('benPageNumbers');
  if (info && prev && next) {
    info.textContent = `পৃষ্ঠা ${state.beneficiariesMeta.page} / ${state.beneficiariesMeta.totalPages} (মোট: ${state.beneficiariesMeta.total})`;
    prev.disabled = state.beneficiariesMeta.page <= 1;
    next.disabled = state.beneficiariesMeta.page >= state.beneficiariesMeta.totalPages;
  }
  if (nums) {
    const totalPages = state.beneficiariesMeta.totalPages;
    const current = state.beneficiariesMeta.page;
    const pages = new Set([1, totalPages, current - 1, current, current + 1]);
    const pageList = [...pages].filter((p) => p >= 1 && p <= totalPages).sort((a, b) => a - b);
    let html = '';
    for (let i = 0; i < pageList.length; i += 1) {
      const p = pageList[i];
      const prevP = i > 0 ? pageList[i - 1] : null;
      if (prevP !== null && p - prevP > 1) {
        html += '<span class="pager-ellipsis">…</span>';
      }
      html += `<button class="pager-num${p === current ? ' active' : ''}" onclick="gotoBenPage(${p})">${p}</button>`;
    }
    nums.innerHTML = html;
  }
}

function renderInst(data = filteredInst) {
  const tbody = document.getElementById('instBody');
  if (!data.length) {
    tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="icon">🏫</div>কোনো রেকর্ড নেই</div></td></tr>';
    return;
  }

  const canEdit = currentUser && (currentUser.role === 'admin' || currentUser.role === 'operator');
  tbody.innerHTML = data
    .map(
      (ins, i) => `
    <tr>
      <td>${i + 1}</td>
      <td><strong>${esc(ins.name)}</strong></td>
      <td><span class="status-badge" style="background:#fef3e0;color:#d68910">${esc(ins.type)}</span></td>
      <td>${esc(ins.union)}</td>
      <td>${esc(ins.students || 0)}</td>
      <td>${esc(ins.head || '-')}</td>
      <td>${esc(ins.phone || '-')}</td>
      <td><div class="table-actions">${canEdit ? `<button class="btn btn-icon btn-edit" title="সম্পাদনা" aria-label="সম্পাদনা" onclick="editInst(${ins.id})">✎</button><button class="btn btn-icon btn-delete" title="মুছুন" aria-label="মুছুন" onclick="deleteInst(${ins.id})">🗑</button>` : ''}</div></td>
    </tr>`
    )
    .join('');
}

function renderUsers() {
  const tbody = document.getElementById('userBody');
  if (!tbody) return;

  if (!state.users.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No user data</div></td></tr>';
    return;
  }

  tbody.innerHTML = state.users
    .map(
      (u, i) => `
    <tr>
      <td>${i + 1}</td><td><strong>${esc(u.name)}</strong></td><td>${esc(u.uname)}</td>
      <td><span class="status-badge" style="background:#e8f0fa;color:#2c5aa0">${u.role === 'admin' ? 'অ্যাডমিন' : u.role === 'viewer' ? 'দর্শক' : 'অপারেটর'}</span></td>
      <td>${u.union === 'all' ? 'সকল ইউনিয়ন' : esc(u.union)}</td>
      <td><span class="status-badge status-active">সক্রিয়</span></td>
      <td><div class="table-actions"><button class="btn btn-icon btn-delete" title="মুছুন" aria-label="মুছুন" onclick="deleteUser(${u.id})">🗑</button></div></td>
    </tr>`
    )
    .join('');
}

function renderUnions() {
  const container = document.getElementById('unionList');
  container.innerHTML = '';

  state.unions.forEach((u) => {
    const name = typeof u === 'string' ? u : u.name;
    const cnt = typeof u === 'string' ? 0 : Number(u.beneficiaries || 0);
    const el = document.createElement('div');
    el.className = 'stat-card union-card';
    el.innerHTML = `<div style="font-size:22px">🗺️</div><div style="font-weight:700;color:var(--primary)">${esc(name)}</div><div style="font-size:12px;color:var(--text-muted)">উপকারভোগী: ${cnt} জন</div>`;
    container.appendChild(el);
  });

  const benUnion = document.getElementById('b-union');
  const instUnion = document.getElementById('i-union');
  const userUnion = document.getElementById('u-union');
  if (benUnion && instUnion && userUnion && state.unions.length) {
    const unionNames = state.unions
      .filter((u) => (typeof u === 'string' ? true : Number(u.beneficiaries || 0) > 0))
      .map((u) => (typeof u === 'string' ? u : u.name));
    const options = unionNames.map((u) => `<option>${esc(u)}</option>`).join('');
    benUnion.innerHTML = options;
    instUnion.innerHTML = options;
    userUnion.innerHTML = `<option value="all">সকল ইউনিয়ন</option>${options}`;
  }
}

function renderRecent() {
  const recent = state.beneficiaries.slice(0, 3);
  document.getElementById('recentBen').innerHTML = recent.length
    ? recent
        .map(
          (b) => `
    <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
      <strong>${esc(b.name)}</strong><br>
      <span style="color:var(--text-muted)">${esc(b.program)} - ${esc(b.union)}</span>
    </div>`
        )
        .join('')
    : '<div style="color:var(--text-muted);font-size:13px">কোনো রেকর্ড নেই</div>';
}

function renderDupList() {
  const tbody = document.getElementById('dupList');
  const dups = state.duplicateGroups;

  if (!dups.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--success)">✅ কোনো দ্বৈত রেকর্ড পাওয়া যায়নি</td></tr>';
    document.getElementById('s-dup').textContent = '0';
    return;
  }

  tbody.innerHTML = dups
    .map(
      (arr) => `<tr><td>${esc(arr[0].name)}</td><td>${esc(arr[0].nid)}</td><td>${esc(arr[0].program)}</td><td>${esc(arr[1].program)}</td><td><span class="status-badge status-inactive">⚠️ দ্বৈত</span></td></tr>`
    )
    .join('');

  document.getElementById('s-dup').textContent = String(dups.length);
}

function updateStats() {
  document.getElementById('s-ben').textContent = String(state.beneficiariesMeta.total);
  document.getElementById('s-inst').textContent = String(state.institutions.length);
  document.getElementById('benBadge').textContent = String(state.beneficiariesMeta.total);
  document.getElementById('instBadge').textContent = String(state.institutions.length);
  document.getElementById('s-dup').textContent = String(state.duplicateCount);
  const prog = state.dashboardReport?.totals?.uniquePrograms ?? 0;
  document.getElementById('s-prog').textContent = String(prog);
}

function renderRowsWithBars(items, mountId) {
  const mount = document.getElementById(mountId);
  if (!mount) return;
  if (!items || !items.length) {
    mount.innerHTML = '<div style="font-size:13px;color:var(--text-muted)">ডেটা পাওয়া যায়নি</div>';
    return;
  }
  const max = Math.max(...items.map((x) => Number(x.count || 0)), 1);
  mount.innerHTML = items.map((x) => {
    const pct = Math.max(4, Math.round((Number(x.count || 0) / max) * 100));
    return `<div class="report-row">
      <div>
        <div>${esc(x.name)}</div>
        <div class="report-bar-wrap"><div class="report-bar" style="width:${pct}%"></div></div>
      </div>
      <strong>${Number(x.count || 0).toLocaleString()}</strong>
    </div>`;
  }).join('');
}

function renderAdvancedReport() {
  const r = state.dashboardReport;
  renderRowsWithBars(r?.topPrograms || [], 'topProgramsReport');
  renderRowsWithBars(r?.topUnions || [], 'topUnionsReport');

  const age = r?.ageBands || {};
  const ageRows = [
    { name: '১৮ বছরের নিচে', count: Number(age.under18 || 0) },
    { name: '১৮-৪০ বছর', count: Number(age.age18to40 || 0) },
    { name: '৪১-৬০ বছর', count: Number(age.age41to60 || 0) },
    { name: '৬০+ বছর', count: Number(age.over60 || 0) },
  ];
  renderRowsWithBars(ageRows, 'ageBandsReport');

  const q = r?.dataQuality || {};
  const total = Math.max(1, Number(state.beneficiariesMeta.total || 0));
  const kpis = [
    { label: 'ফোন যুক্ত', v: Number(q.withPhone || 0) },
    { label: 'জন্মতারিখ যুক্ত', v: Number(q.withDob || 0) },
    { label: 'ঠিকানা যুক্ত', v: Number(q.withAddress || 0) },
  ];
  const mount = document.getElementById('qualityKpis');
  if (mount) {
    mount.innerHTML = kpis.map((k) => {
      const pct = Math.round((k.v / total) * 100);
      return `<div class="kpi"><div class="v">${pct}%</div><div class="l">${esc(k.label)} (${k.v.toLocaleString()})</div></div>`;
    }).join('');
  }
}

function renderProgramsPanel() {
  const mount = document.getElementById('programsPanel');
  if (!mount) return;
  const list = state.beneficiaryPrograms || [];
  if (!list.length) {
    mount.innerHTML = '<div class="stat-card"><div style="color:var(--text-muted)">কোনো কার্যক্রম ডেটা নেই</div></div>';
    return;
  }
  mount.innerHTML = list.map((p) => {
    const name = typeof p === 'string' ? p : p.name;
    const count = Number(typeof p === 'string' ? 0 : p.count || 0);
    return `<div class="stat-card" style="flex-direction:column;align-items:flex-start;gap:8px">
      <div style="font-size:24px">📋</div>
      <div style="font-weight:700;font-size:15px;color:var(--primary)">${esc(name)}</div>
      <div style="font-size:13px;color:var(--text-muted)">উপকারভোগী: ${count.toLocaleString()} জন</div>
      <span class="status-badge status-active">সক্রিয়</span>
    </div>`;
  }).join('');
}

function renderBeneficiaryProgramFilter() {
  const select = document.getElementById('benProgramFilter');
  const modalSelect = document.getElementById('b-prog');
  if (!select && !modalSelect) return;
  const currentFilter = benQuery.program || '';
  const currentModal = modalSelect ? String(modalSelect.value || '') : '';
  const options = (state.beneficiaryPrograms || [])
    .map((p) => {
      const name = typeof p === 'string' ? p : p.name;
      const count = typeof p === 'string' ? null : Number(p.count || 0);
      const label = count === null ? name : `${name} (${count.toLocaleString()})`;
      return `<option value="${esc(name)}">${esc(label)}</option>`;
    })
    .join('');
  if (select) {
    select.innerHTML = `<option value="">সকল কার্যক্রম</option>${options}`;
    select.value = currentFilter;
  }
  if (modalSelect) {
    modalSelect.innerHTML = options || '<option value="">কার্যক্রম নেই</option>';
    if (currentModal && [...modalSelect.options].some((o) => o.value === currentModal)) {
      modalSelect.value = currentModal;
    }
  }
}

function renderBenColumnOptions() {
  const menu = document.getElementById('benColumnsMenu');
  if (!menu) return;
  const selected = new Set(state.benVisibleColumns);
  menu.innerHTML = BEN_COLUMN_DEFS.map(
    (c) => `
      <label class="column-item">
        <input
          type="checkbox"
          ${selected.has(c.key) ? 'checked' : ''}
          onchange="toggleBenColumn('${esc(c.key)}', this.checked)"
        >
        <span>${esc(c.label)}</span>
      </label>`
  ).join('');
}

function toggleBenColumnsMenu(event) {
  event.stopPropagation();
  const menu = document.getElementById('benColumnsMenu');
  if (!menu) return;
  menu.classList.toggle('open');
}

function toggleBenColumn(key, checked) {
  const allowed = new Set(BEN_COLUMN_DEFS.map((c) => c.key));
  if (!allowed.has(key)) return;
  const set = new Set(state.benVisibleColumns);
  if (checked) {
    set.add(key);
  } else {
    set.delete(key);
    if (!set.size) {
      notify('কমপক্ষে একটি কলাম নির্বাচন করতে হবে', 'info');
      renderBenColumnOptions();
      return;
    }
  }
  state.benVisibleColumns = BEN_COLUMN_DEFS.map((c) => c.key).filter((k) => set.has(k));
  saveVisibleBenColumns(state.benVisibleColumns);
  renderBen();
  renderBenColumnOptions();
}

function renderInstitutionTypeOptions() {
  const filterSelect = document.getElementById('instTypeFilter');
  const modalSelect = document.getElementById('i-type');
  const list = state.institutionTypes || [];
  const options = list
    .map((t) => {
      const name = typeof t === 'string' ? t : t.name;
      const count = typeof t === 'string' ? null : Number(t.count || 0);
      const label = count === null ? name : `${name} (${count.toLocaleString()})`;
      return `<option value="${esc(name)}">${esc(label)}</option>`;
    })
    .join('');

  if (filterSelect) {
    const current = String(filterSelect.value || '');
    filterSelect.innerHTML = `<option value="">সকল ধরন</option>${options}`;
    filterSelect.value = current;
  }
  if (modalSelect) {
    const current = String(modalSelect.value || '');
    modalSelect.innerHTML = options || '<option value="">ধরন নেই</option>';
    if (current && [...modalSelect.options].some((o) => o.value === current)) {
      modalSelect.value = current;
    }
  }
}

function filterTable(type, val) {
  const search = String(val || '');
  if (type === 'ben') {
    const input = document.getElementById('benSearchInput');
    if (input) input.value = search;
    applyBenSearch();
  } else {
    const q = search.toLowerCase();
    filteredInst = state.institutions.filter(
      (i) => i.name.toLowerCase().includes(q) || (i.union && i.union.toLowerCase().includes(q))
    );
    renderInst();
  }
}

async function filterByProgram(v) {
  benQuery.program = String(v || '');
  benQuery.page = 1;
  await loadBeneficiaries();
  renderBen();
  updateStats();
  renderRecent();
}

function filterByType(v) {
  filteredInst = v ? state.institutions.filter((i) => i.type === v) : [...state.institutions];
  renderInst();
}

async function changeBenPage(step) {
  const nextPage = benQuery.page + step;
  if (nextPage < 1 || nextPage > state.beneficiariesMeta.totalPages) return;
  benQuery.page = nextPage;
  await loadBeneficiaries();
  renderBen();
  renderRecent();
}

async function applyBenSearch() {
  const input = document.getElementById('benSearchInput');
  benQuery.q = input ? String(input.value || '').trim() : '';
  benQuery.page = 1;
  if (benSearchDebounce) clearTimeout(benSearchDebounce);
  benSearchDebounce = setTimeout(async () => {
    await loadBeneficiaries();
    renderBen();
    updateStats();
    renderRecent();
  }, 50);
}

async function resetBenSearch() {
  const input = document.getElementById('benSearchInput');
  if (input) input.value = '';
  benQuery.q = '';
  benQuery.program = '';
  benQuery.page = 1;
  const programFilter = document.getElementById('benProgramFilter');
  if (programFilter) programFilter.value = '';
  await loadBeneficiaries();
  renderBen();
  updateStats();
  renderRecent();
}

async function gotoBenPage(page) {
  const p = Number(page);
  if (!Number.isFinite(p)) return;
  if (p < 1 || p > state.beneficiariesMeta.totalPages || p === benQuery.page) return;
  benQuery.page = p;
  await loadBeneficiaries();
  renderBen();
  renderRecent();
}

function openModal(type, clear = true) {
  if (clear) {
    editingId[type] = null;
    clearForm(type);
  }
  document.getElementById('modal-' + type).classList.add('open');
}

function closeModal(type) {
  document.getElementById('modal-' + type).classList.remove('open');
}

function clearForm(type) {
  if (type === 'ben') {
    ['b-name', 'b-nid', 'b-phone', 'b-dob', 'b-father', 'b-mother', 'b-addr'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    document.getElementById('b-status').value = 'active';
    document.getElementById('benDupWarn').classList.remove('show');
  }
  if (type === 'inst') {
    ['i-name', 'i-head', 'i-phone', 'i-addr', 'i-students'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  }
  if (type === 'user') {
    ['u-name', 'u-uname', 'u-pass'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  }
  if (type === 'program') {
    const input = document.getElementById('p-name');
    if (input) input.value = '';
  }
  if (type === 'officer') {
    ['o-name', 'o-designation', 'o-join-date', 'o-telephone', 'o-mobile', 'o-email'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
    const file = document.getElementById('o-photo');
    if (file) file.value = '';
  }
}

function checkNidLive(nid) {
  if (nid.length >= 10) {
    const exists = state.beneficiaries.find((b) => b.nid === nid && b.id !== editingId.ben);
    document.getElementById('benDupWarn').classList.toggle('show', !!exists);
  }
}

async function saveBen() {
  const name = document.getElementById('b-name').value.trim();
  const nid = document.getElementById('b-nid').value.trim();
  if (!name || !nid) return notify('নাম ও NID আবশ্যক', 'info');

  const payload = {
    name,
    nid,
    program: document.getElementById('b-prog').value,
    union: document.getElementById('b-union').value,
    phone: document.getElementById('b-phone').value,
    dob: document.getElementById('b-dob').value,
    father: document.getElementById('b-father').value,
    mother: document.getElementById('b-mother').value,
    addr: document.getElementById('b-addr').value,
    status: document.getElementById('b-status').value,
  };

  try {
    if (editingId.ben) {
      await api(`/api/beneficiaries/item.php?id=${editingId.ben}&op=update`, { method: 'POST', body: JSON.stringify(payload) });
    } else {
      await api('/api/beneficiaries/index.php', { method: 'POST', body: JSON.stringify(payload) });
    }
    closeModal('ben');
    await refreshAll();
  } catch (err) {
    notify(err.message, 'error');
  }
}

async function saveInst() {
  const name = document.getElementById('i-name').value.trim();
  if (!name) return notify('প্রতিষ্ঠানের নাম আবশ্যক', 'info');

  const payload = {
    name,
    type: document.getElementById('i-type').value,
    union: document.getElementById('i-union').value,
    students: Number(document.getElementById('i-students').value || 0),
    head: document.getElementById('i-head').value,
    phone: document.getElementById('i-phone').value,
    addr: document.getElementById('i-addr').value,
  };

  try {
    if (editingId.inst) {
      await api(`/api/institutions/item.php?id=${editingId.inst}&op=update`, { method: 'POST', body: JSON.stringify(payload) });
    } else {
      await api('/api/institutions/index.php', { method: 'POST', body: JSON.stringify(payload) });
    }
    closeModal('inst');
    await refreshAll();
  } catch (err) {
    notify(err.message, 'error');
  }
}

async function saveUser() {
  const name = document.getElementById('u-name').value.trim();
  const uname = document.getElementById('u-uname').value.trim();
  const password = document.getElementById('u-pass').value;
  if (!name || !uname || !password) return notify('নাম, ইউজারনেম ও পাসওয়ার্ড আবশ্যক', 'info');

  try {
    await api('/api/users/index.php', {
      method: 'POST',
      body: JSON.stringify({
        name,
        uname,
        password,
        role: document.getElementById('u-role').value,
        union: document.getElementById('u-union').value,
      }),
    });

    closeModal('user');
    await refreshAll();
  } catch (err) {
    notify(err.message, 'error');
  }
}

async function saveProgram() {
  const name = document.getElementById('p-name').value.trim();
  if (!name) return notify('কার্যক্রমের নাম আবশ্যক', 'info');

  try {
    const result = await api('/api/beneficiaries/programs.php', {
      method: 'POST',
      body: JSON.stringify({ name }),
    });
    closeModal('program');
    await refreshAll();
    notify(result.created ? 'নতুন কার্যক্রম যোগ হয়েছে' : 'এই কার্যক্রমটি আগে থেকেই আছে', 'success');
  } catch (err) {
    notify(err.message, 'error');
  }
}

function openOfficerModal() {
  const p = state.officerProfile;
  if (!p) {
    notify('Officer profile load হয়নি, আবার চেষ্টা করুন', 'error');
    return;
  }
  document.getElementById('o-name').value = p.name || '';
  document.getElementById('o-designation').value = p.designation || '';
  document.getElementById('o-join-date').value = p.joinDate || '';
  document.getElementById('o-telephone').value = p.telephone || '';
  document.getElementById('o-mobile').value = p.mobile || '';
  document.getElementById('o-email').value = p.email || '';
  const preview = document.getElementById('o-photo-preview');
  if (preview) preview.src = p.photoPath || 'media/profile.jpeg';
  const file = document.getElementById('o-photo');
  if (file) file.value = '';
  openModal('officer', false);
}

async function saveOfficerProfile() {
  const payload = {
    name: document.getElementById('o-name').value.trim(),
    designation: document.getElementById('o-designation').value.trim(),
    joinDate: document.getElementById('o-join-date').value,
    telephone: document.getElementById('o-telephone').value.trim(),
    mobile: document.getElementById('o-mobile').value.trim(),
    email: document.getElementById('o-email').value.trim(),
  };
  if (!payload.name || !payload.designation) {
    notify('নাম এবং পদবি আবশ্যক', 'info');
    return;
  }

  try {
    const profile = await api('/api/officer/profile.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    const fileInput = document.getElementById('o-photo');
    if (fileInput && fileInput.files && fileInput.files[0]) {
      const form = new FormData();
      form.append('photo', fileInput.files[0]);
      const res = await fetch(withBase('/api/officer/photo.php'), {
        method: 'POST',
        credentials: 'include',
        body: form,
      });
      const result = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(result?.error || 'Photo upload failed');
      }
      profile.photoPath = result.photoPath || profile.photoPath;
    }

    state.officerProfile = profile;
    renderOfficerProfile();
    closeModal('officer');
    notify('অফিসার তথ্য আপডেট হয়েছে', 'success');
  } catch (err) {
    notify(err.message || 'Update failed', 'error');
  }
}

function editBen(id) {
  const b = state.beneficiaries.find((x) => x.id === id);
  if (!b) return;

  editingId.ben = id;
  document.getElementById('b-name').value = b.name;
  document.getElementById('b-nid').value = b.nid;
  document.getElementById('b-phone').value = b.phone || '';
  document.getElementById('b-dob').value = b.dob || '';
  document.getElementById('b-father').value = b.father || '';
  document.getElementById('b-mother').value = b.mother || '';
  document.getElementById('b-addr').value = b.addr || '';
  document.getElementById('b-prog').value = b.program;
  document.getElementById('b-union').value = b.union;
  document.getElementById('b-status').value = b.status;
  openModal('ben', false);
}

function editInst(id) {
  const ins = state.institutions.find((x) => x.id === id);
  if (!ins) return;

  editingId.inst = id;
  document.getElementById('i-name').value = ins.name;
  document.getElementById('i-type').value = ins.type;
  document.getElementById('i-union').value = ins.union;
  document.getElementById('i-students').value = ins.students || '';
  document.getElementById('i-head').value = ins.head || '';
  document.getElementById('i-phone').value = ins.phone || '';
  document.getElementById('i-addr').value = ins.addr || '';
  openModal('inst', false);
}

async function deleteBen(id) {
  if (!confirm('মুছে ফেলবেন?')) return;
  try {
    await api(`/api/beneficiaries/item.php?id=${id}&op=delete`, { method: 'POST' });
    await refreshAll();
  } catch (err) {
    notify(err.message, 'error');
  }
}

async function deleteInst(id) {
  if (!confirm('মুছে ফেলবেন?')) return;
  try {
    await api(`/api/institutions/item.php?id=${id}&op=delete`, { method: 'POST' });
    await refreshAll();
  } catch (err) {
    notify(err.message, 'error');
  }
}

async function deleteUser(id) {
  if (!confirm('মুছে ফেলবেন?')) return;
  try {
    await api(`/api/users/item.php?id=${id}&op=delete`, { method: 'POST' });
    await refreshAll();
  } catch (err) {
    notify(err.message, 'error');
  }
}

async function checkDuplicate() {
  const val = document.getElementById('dupSearchInput').value.trim();
  if (!val) return notify('NID বা ফোন নম্বর দিন', 'info');

  try {
    const result = await api('/api/duplicate/check.php', {
      method: 'POST',
      body: JSON.stringify({ value: val }),
    });

    const matches = result.matches || [];
    const res = document.getElementById('dupResult');

    if (!matches.length) {
      res.innerHTML = '<div class="alert alert-success">✅ এই তথ্যে কোনো রেকর্ড পাওয়া যায়নি।</div>';
      return;
    }

    if (matches.length === 1) {
      const m = matches[0];
      res.innerHTML = `<div class="alert alert-warning">⚠️ একটি রেকর্ড পাওয়া গেছে: <strong>${esc(m.name)}</strong> - ${esc(m.program)} (${esc(m.union)})</div>`;
      return;
    }

    res.innerHTML = `<div class="alert alert-danger">🚨 দ্বৈত সুবিধা সনাক্ত! <strong>${esc(matches[0].name)}</strong> ইতোমধ্যে ${matches.length}টি কার্যক্রমে নথিভুক্ত:<br>${matches.map((m) => ` • ${esc(m.program)} (${esc(m.union)})`).join('<br>')}</div>`;
  } catch (err) {
    notify(err.message, 'error');
  }
}

function afterLogin() {
  document.getElementById('loginScreen').style.display = 'none';
  document.getElementById('app').style.display = 'block';
  const roleBn = currentUser.role === 'admin' ? 'অ্যাডমিন' : currentUser.role === 'viewer' ? 'দর্শক' : 'অপারেটর';
  const roleTitle = currentUser.role === 'admin' ? 'প্রশাসক' : currentUser.role === 'viewer' ? 'ভিউয়ার' : 'অপারেটর';
  document.getElementById('currentUserLabel').textContent = roleBn;
  const sub = document.getElementById('currentUserSub');
  if (sub) sub.textContent = roleTitle;
  const avatar = document.querySelector('.user-avatar');
  if (avatar) {
    avatar.textContent = currentUser.role === 'admin' ? 'অ' : currentUser.role === 'viewer' ? 'দ' : 'ও';
  }
  updateTopDateTime();

  applyRoleVisibility();
  refreshAll();
}

async function boot() {
  ['username', 'password'].forEach((id) => {
    const input = document.getElementById(id);
    if (!input) return;
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        doLogin();
      }
    });
  });
  document.querySelectorAll('.modal-overlay').forEach((el) =>
    el.addEventListener('click', function onOverlayClick(e) {
      if (e.target === this) this.classList.remove('open');
    })
  );
  document.addEventListener('click', (e) => {
    const menu = document.getElementById('benColumnsMenu');
    const trigger = e.target && typeof e.target.closest === 'function' ? e.target.closest('.column-picker') : null;
    if (menu && !trigger) {
      menu.classList.remove('open');
    }
  });
  const photoInput = document.getElementById('o-photo');
  if (photoInput) {
    photoInput.addEventListener('change', () => {
      const file = photoInput.files && photoInput.files[0];
      const preview = document.getElementById('o-photo-preview');
      if (!preview) return;
      if (!file) {
        preview.src = state.officerProfile?.photoPath || 'media/profile.jpeg';
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        preview.src = String(reader.result || '');
      };
      reader.readAsDataURL(file);
    });
  }
  updateTopDateTime();
  setInterval(updateTopDateTime, 1000);

  try {
    const result = await api('/api/auth/me.php');
    currentUser = {
      id: result.user.id,
      name: result.user.name,
      role: result.user.role,
      username: result.user.username,
    };
    afterLogin();
  } catch {
    document.getElementById('loginScreen').style.display = 'grid';
    document.getElementById('app').style.display = 'none';
  }
}

boot();

