<?php
require_once 'includes/db.php';
$pageTitle = 'Cash & Bank Book — Diesel Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
:root {
    --color-in:  #198754;
    --color-out: #dc3545;
    --sidebar-w: 240px;
}

body { background: #f5f6fa; font-size: 14px; }

/* ── Sidebar ── */
.sidebar {
    width: var(--sidebar-w);
    min-height: 100vh;
    background: #1a2332;
    color: #c8d0e0;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0;
    z-index: 1040;
}
.sidebar .brand {
    padding: 1.25rem 1rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex; align-items: center; gap: .6rem;
}
.sidebar .brand i { font-size: 1.5rem; color: #4da6ff; }
.sidebar .brand span { font-size: 1rem; font-weight: 600; color: #fff; line-height: 1.2; }
.sidebar .brand small { font-size: .7rem; color: #8899aa; display: block; }

.sidebar .nav-section { padding: .75rem 1rem .25rem; font-size: .65rem; text-transform: uppercase; letter-spacing: .08em; color: #5a6a7a; }
.sidebar .nav-link {
    color: #c8d0e0; padding: .55rem 1rem; border-radius: 6px; margin: 1px 6px;
    display: flex; align-items: center; gap: .6rem; font-size: .82rem;
}
.sidebar .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
.sidebar .nav-link.active { background: #4da6ff22; color: #4da6ff; font-weight: 500; }
.sidebar .nav-link i { font-size: 1rem; width: 18px; }

/* ── Main ── */
.main-wrap { margin-left: var(--sidebar-w); }
.topbar {
    background: #fff; border-bottom: 1px solid #e5e7eb;
    padding: .75rem 1.5rem;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.topbar .page-title { font-size: 1rem; font-weight: 600; margin: 0; }

.content { padding: 1.5rem; }

/* ── Summary Cards ── */
.summary-card {
    border: none; border-radius: 12px; padding: 1.1rem 1.25rem;
}
.summary-card .s-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: #6c757d; margin-bottom: .3rem; }
.summary-card .s-value { font-size: 1.35rem; font-weight: 700; margin: 0; }
.card-inflow  { background: #e8f5e9; }
.card-outflow { background: #fdecea; }
.card-balance { background: #e3f2fd; }
.card-inflow  .s-value { color: #1b5e20; }
.card-outflow .s-value { color: #b71c1c; }
.card-balance .s-value { color: #0d47a1; }

/* ── Table card ── */
.table-card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden; }
.table-card .tc-header { padding: .85rem 1.25rem; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
.table-card .tc-title { font-weight: 600; font-size: .9rem; margin: 0; }
.table-card table { margin: 0; font-size: .8rem; }
.table-card thead th { background: #f8f9fa; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; border-bottom: 1px solid #e5e7eb; padding: .6rem .85rem; white-space: nowrap; }
.table-card tbody td { padding: .65rem .85rem; vertical-align: middle; border-color: #f0f0f0; }
.table-card tbody tr:hover { background: #fafbfc; }
.table-card tbody tr:last-child td { border-bottom: none; }

/* ── Badges ── */
.badge-in  { background: #d1fae5; color: #065f46; }
.badge-out { background: #fee2e2; color: #991b1b; }
.amount-in  { color: var(--color-in);  font-weight: 600; }
.amount-out { color: var(--color-out); font-weight: 600; }
.running-bal { font-size: .72rem; color: #6c757d; }

/* ── Type toggle in modal ── */
.type-toggle .btn { border-radius: 0; }
.type-toggle .btn:first-child { border-radius: 6px 0 0 6px; }
.type-toggle .btn:last-child  { border-radius: 0 6px 6px 0; }

/* ── Misc ── */
.empty-state { text-align: center; padding: 3rem 1rem; color: #adb5bd; }
.empty-state i { font-size: 2.5rem; display: block; margin-bottom: .5rem; }
.filter-bar { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.filter-bar .form-select, .filter-bar .form-control { font-size: .78rem; padding: .3rem .6rem; height: 34px; }

@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); transition: transform .3s; }
    .sidebar.show { transform: translateX(0); }
    .main-wrap { margin-left: 0; }
}
</style>
</head>
<body>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
<aside class="sidebar">
    <div class="brand">
        <i class="bi bi-droplet-fill"></i>
        <div><span>Diesel Manager</span><small>Accounting Module</small></div>
    </div>
    <nav class="mt-2">
        <div class="nav-section">Books</div>
        <a href="#" class="nav-link active" id="nav-cash" onclick="switchBook('cash'); return false;">
            <i class="bi bi-wallet2"></i> Cash Book
        </a>
        <a href="#" class="nav-link" id="nav-bank" onclick="switchBook('bank'); return false;">
            <i class="bi bi-bank2"></i> Bank Book
        </a>
        <div class="nav-section mt-3">Quick actions</div>
        <a href="#" class="nav-link" onclick="openAddModal(); return false;">
            <i class="bi bi-plus-circle"></i> Add Transaction
        </a>
        <a href="install.php" class="nav-link">
            <i class="bi bi-database-gear"></i> DB Setup
        </a>
    </nav>
</aside>

<!-- ═══════════════════ MAIN ═══════════════════ -->
<div class="main-wrap">

    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <h1 class="page-title" id="topbar-title">
                <i class="bi bi-wallet2 me-2 text-primary"></i>Cash Book
            </h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-secondary border" id="topbar-count">0 records</span>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="bi bi-plus-lg me-1"></i>Add Transaction
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Summary Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="summary-card card-inflow">
                    <div class="s-label"><i class="bi bi-arrow-down-left me-1"></i>Total Inflow</div>
                    <p class="s-value" id="sum-in">PKR 0</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card card-outflow">
                    <div class="s-label"><i class="bi bi-arrow-up-right me-1"></i>Total Outflow</div>
                    <p class="s-value" id="sum-out">PKR 0</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card card-balance">
                    <div class="s-label"><i class="bi bi-wallet me-1"></i>Balance</div>
                    <p class="s-value" id="sum-bal">PKR 0</p>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="table-card">
            <div class="tc-header">
                <h2 class="tc-title">Transactions</h2>
                <div class="filter-bar">
                    <select class="form-select" id="f-type" onchange="loadData()">
                        <option value="">All types</option>
                        <option value="in">Inflow</option>
                        <option value="out">Outflow</option>
                    </select>
                    <select class="form-select" id="f-cat" onchange="loadData()">
                        <option value="">All categories</option>
                    </select>
                    <input type="date" class="form-control" id="f-from" onchange="loadData()" title="From date">
                    <input type="date" class="form-control" id="f-to"   onchange="loadData()" title="To date">
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()" title="Clear filters">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="txn-table">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th style="width:100px">Date</th>
                            <th style="width:60px">Type</th>
                            <th>Description</th>
                            <th style="width:130px">Category</th>
                            <th style="width:90px">Ref No.</th>
                            <th style="width:120px" class="text-end">Amount</th>
                            <th style="width:120px" class="text-end">Balance</th>
                            <th style="width:70px" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="txn-tbody">
                        <tr><td colspan="9" class="empty-state"><i class="bi bi-hourglass-split"></i>Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main-wrap -->


<!-- ═══════════════════ ADD / EDIT MODAL ═══════════════════ -->
<div class="modal fade" id="txnModal" tabindex="-1" aria-labelledby="txnModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header py-3">
        <h5 class="modal-title fs-6 fw-bold" id="txnModalLabel">Add Transaction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="m-id">
        <input type="hidden" id="m-book">

        <!-- Type Toggle -->
        <div class="mb-3">
            <label class="form-label small fw-semibold">Type</label><br>
            <div class="btn-group type-toggle w-100" role="group">
                <button type="button" class="btn btn-success active" id="btn-in" onclick="setType('in')">
                    <i class="bi bi-arrow-down-left me-1"></i>Inflow
                </button>
                <button type="button" class="btn btn-outline-danger" id="btn-out" onclick="setType('out')">
                    <i class="bi bi-arrow-up-right me-1"></i>Outflow
                </button>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-6">
                <label class="form-label small fw-semibold">Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" id="m-date">
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold">Reference / Voucher No.</label>
                <input type="text" class="form-control form-control-sm" id="m-ref" placeholder="e.g. VCH-001">
            </div>
        </div>

        <div class="mb-2 mt-2">
            <label class="form-label small fw-semibold">Description <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-sm" id="m-desc" placeholder="e.g. Diesel purchase — Lahore depot">
        </div>

        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label small fw-semibold">Category <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="m-cat">
                    <option value="Diesel purchase">Diesel purchase</option>
                    <option value="Diesel sale">Diesel sale</option>
                    <option value="Supplier payment">Supplier payment</option>
                    <option value="Customer receipt">Customer receipt</option>
                    <option value="Transport cost">Transport cost</option>
                    <option value="Salary">Salary</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Tax / duty">Tax / duty</option>
                    <option value="Bank deposit">Bank deposit</option>
                    <option value="Bank withdrawal">Bank withdrawal</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label small fw-semibold">Amount (PKR) <span class="text-danger">*</span></label>
                <input type="number" class="form-control form-control-sm" id="m-amount" placeholder="0.00" min="0.01" step="0.01">
            </div>
        </div>

        <div class="mb-1">
            <label class="form-label small fw-semibold">Notes (optional)</label>
            <textarea class="form-control form-control-sm" id="m-notes" rows="2" placeholder="Any additional details…"></textarea>
        </div>

        <div id="modal-error" class="alert alert-danger py-2 mt-2 d-none small"></div>
    </div>
    <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" id="btn-save" onclick="saveTransaction()">
            <i class="bi bi-check-lg me-1"></i>Save Transaction
        </button>
    </div>
</div>
</div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal fade" id="delModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-sm modal-dialog-centered">
<div class="modal-content">
    <div class="modal-header py-3 border-0">
        <h5 class="modal-title fs-6">Delete transaction?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body py-0 small text-muted">This cannot be undone.</div>
    <div class="modal-footer py-2 border-0">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="btn-del-confirm">Delete</button>
    </div>
</div>
</div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:2000">
    <div id="appToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body small" id="toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ════════════════════════════════════════════════
// State
// ════════════════════════════════════════════════
let currentBook = 'cash';
let currentType = 'in';
let deleteId    = null;
const txnModal  = new bootstrap.Modal(document.getElementById('txnModal'));
const delModal  = new bootstrap.Modal(document.getElementById('delModal'));
const toastEl   = document.getElementById('appToast');
const toast     = new bootstrap.Toast(toastEl, { delay: 3000 });

// ════════════════════════════════════════════════
// Book Switch
// ════════════════════════════════════════════════
function switchBook(book) {
    currentBook = book;
    ['cash','bank'].forEach(b => {
        document.getElementById('nav-'+b).classList.toggle('active', b === book);
    });
    const icon  = book === 'cash' ? 'bi-wallet2' : 'bi-bank2';
    const label = book === 'cash' ? 'Cash Book'  : 'Bank Book';
    document.getElementById('topbar-title').innerHTML = `<i class="bi ${icon} me-2 text-primary"></i>${label}`;
    clearFilters(false);
    loadData();
}

// ════════════════════════════════════════════════
// Load data from API
// ════════════════════════════════════════════════
function loadData() {
    const type = document.getElementById('f-type').value;
    const cat  = document.getElementById('f-cat').value;
    const from = document.getElementById('f-from').value;
    const to   = document.getElementById('f-to').value;

    const params = new URLSearchParams({ action:'list', book: currentBook });
    if (type) params.append('type', type);
    if (cat)  params.append('cat',  cat);
    if (from) params.append('from', from);
    if (to)   params.append('to',   to);

    fetch('api.php?' + params)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            renderSummary(res.summary);
            renderCategories(res.categories);
            renderRows(res.rows);
            document.getElementById('topbar-count').textContent = res.rows.length + ' record' + (res.rows.length !== 1 ? 's' : '');
        });
}

// ════════════════════════════════════════════════
// Render helpers
// ════════════════════════════════════════════════
function fmt(n) {
    return 'PKR ' + parseFloat(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function renderSummary(s) {
    const inflow  = parseFloat(s.total_in  || 0);
    const outflow = parseFloat(s.total_out || 0);
    const bal     = inflow - outflow;
    document.getElementById('sum-in').textContent  = fmt(inflow);
    document.getElementById('sum-out').textContent = fmt(outflow);
    const balEl = document.getElementById('sum-bal');
    balEl.textContent  = fmt(bal);
    balEl.style.color  = bal < 0 ? '#b71c1c' : '#0d47a1';
}

function renderCategories(cats) {
    const sel = document.getElementById('f-cat');
    const cur = sel.value;
    sel.innerHTML = '<option value="">All categories</option>' +
        cats.map(c => `<option value="${escHtml(c)}"${c===cur?' selected':''}>${escHtml(c)}</option>`).join('');
}

function renderRows(rows) {
    const tbody = document.getElementById('txn-tbody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="9"><div class="empty-state"><i class="bi bi-inbox"></i>No transactions found.</div></td></tr>`;
        return;
    }

    // compute running balance from all rows sorted asc by date+id
    const sorted = [...rows].sort((a,b) => a.txn_date.localeCompare(b.txn_date)||a.id-b.id);
    let run = 0;
    const balMap = {};
    sorted.forEach(t => { run += t.type==='in' ? +t.amount : -t.amount; balMap[t.id] = run; });

    tbody.innerHTML = rows.map((t,i) => `
    <tr>
        <td class="text-muted" style="font-size:.7rem">${i+1}</td>
        <td style="font-size:.75rem">${t.txn_date}</td>
        <td><span class="badge badge-${t.type}">${t.type==='in'?'In':'Out'}</span></td>
        <td>
            <div class="fw-medium" style="font-size:.82rem">${escHtml(t.description)}</div>
            ${t.notes ? `<div style="font-size:.7rem;color:#6c757d">${escHtml(t.notes)}</div>` : ''}
        </td>
        <td style="font-size:.75rem;color:#6c757d">${escHtml(t.category)}</td>
        <td style="font-size:.72rem;color:#6c757d">${t.reference || '—'}</td>
        <td class="text-end amount-${t.type}">${t.type==='in'?'+':'−'} ${parseFloat(t.amount).toLocaleString('en-PK',{minimumFractionDigits:2})}</td>
        <td class="text-end running-bal">${fmt(balMap[t.id])}</td>
        <td class="text-center">
            <button class="btn btn-sm btn-link p-0 me-1 text-secondary" onclick="editTransaction(${t.id})" title="Edit">
                <i class="bi bi-pencil-square" style="font-size:.85rem"></i>
            </button>
            <button class="btn btn-sm btn-link p-0 text-danger" onclick="confirmDelete(${t.id})" title="Delete">
                <i class="bi bi-trash3" style="font-size:.85rem"></i>
            </button>
        </td>
    </tr>`).join('');
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ════════════════════════════════════════════════
// Add / Edit Modal
// ════════════════════════════════════════════════
function openAddModal() {
    document.getElementById('txnModalLabel').textContent = 'Add Transaction';
    document.getElementById('m-id').value   = '';
    document.getElementById('m-book').value = currentBook;
    document.getElementById('m-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('m-desc').value = '';
    document.getElementById('m-ref').value  = '';
    document.getElementById('m-notes').value= '';
    document.getElementById('m-amount').value = '';
    document.getElementById('m-cat').value  = 'Diesel purchase';
    document.getElementById('modal-error').classList.add('d-none');
    setType('in');
    txnModal.show();
}

function editTransaction(id) {
    fetch(`api.php?action=get&id=${id}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const t = res.row;
            document.getElementById('txnModalLabel').textContent = 'Edit Transaction';
            document.getElementById('m-id').value    = t.id;
            document.getElementById('m-book').value  = t.book;
            document.getElementById('m-date').value  = t.txn_date;
            document.getElementById('m-desc').value  = t.description;
            document.getElementById('m-ref').value   = t.reference;
            document.getElementById('m-notes').value = t.notes;
            document.getElementById('m-amount').value= t.amount;
            document.getElementById('m-cat').value   = t.category;
            document.getElementById('modal-error').classList.add('d-none');
            setType(t.type);
            txnModal.show();
        });
}

function setType(t) {
    currentType = t;
    const btnIn  = document.getElementById('btn-in');
    const btnOut = document.getElementById('btn-out');
    btnIn.className  = t==='in'  ? 'btn btn-success active' : 'btn btn-outline-success';
    btnOut.className = t==='out' ? 'btn btn-danger  active' : 'btn btn-outline-danger';
}

function saveTransaction() {
    const id     = document.getElementById('m-id').value;
    const book   = document.getElementById('m-book').value || currentBook;
    const date   = document.getElementById('m-date').value;
    const desc   = document.getElementById('m-desc').value.trim();
    const cat    = document.getElementById('m-cat').value;
    const amount = parseFloat(document.getElementById('m-amount').value);
    const ref    = document.getElementById('m-ref').value.trim();
    const notes  = document.getElementById('m-notes').value.trim();
    const errEl  = document.getElementById('modal-error');

    if (!date || !desc || isNaN(amount) || amount <= 0) {
        errEl.textContent = 'Please fill in date, description, and a valid amount.';
        errEl.classList.remove('d-none');
        return;
    }
    errEl.classList.add('d-none');

    const payload = { book, type: currentType, txn_date: date, description: desc, category: cat, amount, reference: ref, notes };
    const action  = id ? 'update' : 'create';
    if (id) payload.id = id;

    const btn = document.getElementById('btn-save');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    fetch(`api.php?action=${action}`, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Transaction';
            if (!res.success) { errEl.textContent = res.message; errEl.classList.remove('d-none'); return; }
            txnModal.hide();
            showToast(id ? 'Transaction updated.' : 'Transaction saved.', 'success');
            loadData();
        })
        .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Transaction'; });
}

// ════════════════════════════════════════════════
// Delete
// ════════════════════════════════════════════════
function confirmDelete(id) {
    deleteId = id;
    delModal.show();
}

document.getElementById('btn-del-confirm').addEventListener('click', () => {
    if (!deleteId) return;
    fetch('api.php?action=delete', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id: deleteId }) })
        .then(r => r.json())
        .then(res => {
            delModal.hide();
            if (res.success) { showToast('Transaction deleted.', 'danger'); loadData(); }
        });
    deleteId = null;
});

// ════════════════════════════════════════════════
// Utilities
// ════════════════════════════════════════════════
function clearFilters(reload = true) {
    document.getElementById('f-type').value = '';
    document.getElementById('f-cat').value  = '';
    document.getElementById('f-from').value = '';
    document.getElementById('f-to').value   = '';
    if (reload) loadData();
}

function showToast(msg, type='success') {
    document.getElementById('toast-msg').textContent = msg;
    toastEl.className = `toast align-items-center text-white border-0 bg-${type}`;
    toast.show();
}

// Sidebar toggle on mobile
document.getElementById('sidebarToggle').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('show');
});

// ── Init ──
loadData();
</script>
</body>
</html>
