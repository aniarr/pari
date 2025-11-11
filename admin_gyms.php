<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

/* ---------- ADMIN CHECK ---------- */
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: adminlogin.php?action=login");
    exit;
}

/* ---------- LOGOUT ---------- */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: adminlogin.php?action=login");
    exit;
}

/* ---------- DB ---------- */
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

/* ---------- APPROVE / REJECT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gym_id'], $_POST['action'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['msg'] = "Invalid CSRF token.";
    } else {
        $gym_id = (int)$_POST['gym_id'];
        $new_status = ($_POST['action'] === 'approve') ? 1 : 2;

        $stmt = $conn->prepare("UPDATE gyms SET status = ? WHERE gym_id = ?");
        $stmt->bind_param("ii", $new_status, $gym_id);
        $stmt->execute();
        $stmt->close();

        $statusText = $new_status == 1 ? 'approved' : 'rejected';
        $_SESSION['msg'] = "Gym #$gym_id has been <strong>$statusText</strong>.";
    }
    header("Location: admin_gyms.php");
    exit;
}

/* ---------- DELETE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gym'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['msg'] = "Invalid CSRF token.";
    } else {
        $gym_id = (int)$_POST['delete_gym'];

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT filename FROM gym_images WHERE gym_id = ?");
            $stmt->bind_param("i", $gym_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $path = __DIR__ . '/pari/uploads/gyms/' . $row['filename'];
                if (file_exists($path)) @unlink($path);
            }
            $stmt->close();

            $stmt = $conn->prepare("SELECT gym_image FROM gyms WHERE gym_id = ?");
            $stmt->bind_param("i", $gym_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res['gym_image']) {
                $path = __DIR__ . '/pari/uploads/gyms/' . $res['gym_image'];
                if (file_exists($path)) @unlink($path);
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM gym_images WHERE gym_id = ?");
            $stmt->bind_param("i", $gym_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM gyms WHERE gym_id = ?");
            $stmt->bind_param("i", $gym_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $_SESSION['msg'] = "Gym deleted permanently.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['msg'] = "Delete failed.";
        }
    }
    header("Location: admin_gyms.php");
    exit;
}

/* ---------- SEARCH + FETCH GYMS + IMAGES ---------- */
$search = trim($_GET['search'] ?? '');
$sql = "SELECT g.*, o.owner_name FROM gyms g LEFT JOIN ownerlog o ON g.owner_id = o.owner_id WHERE 1=1";
$params = []; $types = "";

if ($search !== '') {
    $sql .= " AND (g.gym_name LIKE ? OR g.gym_email LIKE ? OR g.location LIKE ? OR g.gym_city LIKE ?)";
    $term = "%$search%";
    $params = array_fill(0, 4, $term);
    $types = "ssss";
}
$sql .= " ORDER BY g.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $refs = [];
    foreach ($params as $key => $value) $refs[$key] = &$params[$key];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
$stmt->execute();
$gyms_result = $stmt->get_result();

$gyms = [];
while ($gym = $gyms_result->fetch_assoc()) {
    $gym_id = $gym['gym_id'];

    $images = [];
    if ($gym['gym_image']) {
        $images[] = $gym['gym_image'];
    }

    $imgStmt = $conn->prepare("SELECT filename FROM gym_images WHERE gym_id = ? ORDER BY uploaded_at DESC");
    $imgStmt->bind_param("i", $gym_id);
    $imgStmt->execute();
    $imgRes = $imgStmt->get_result();
    while ($row = $imgRes->fetch_assoc()) {
        if (!in_array($row['filename'], $images)) {
            $images[] = $row['filename'];
        }
    }
    $imgStmt->close();

    $gym['images'] = $images;
    $gyms[] = $gym;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin – Gyms | RawFit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .glass { backdrop-filter: blur(12px); background: rgba(30,30,40,.6); border: 1px solid rgba(255,255,255,.1); }
        .badge { @apply px-3 py-1 text-xs font-bold rounded-full; }
        .badge-pending { @apply bg-yellow-900/40 text-yellow-300 border border-yellow-700; }
        .badge-approved { @apply bg-green-900/40 text-green-300 border border-green-700; }
        .badge-rejected { @apply bg-red-900/40 text-red-300 border border-red-700; }
        .lightbox { transition: opacity .3s; }
        .lightbox img { max-w-full; max-h-full; object-contain; }
    </style>
</head>
<body class="bg-gradient-to-br from-black via-gray-900 to-black text-gray-100 min-h-screen">

<!-- NAV -->
<nav class="fixed top-0 inset-x-0 bg-black/90 backdrop-blur-md border-b border-gray-800 z-50">
    <div class="max-w-7xl mx-auto px-4 flex justify-between h-16 items-center">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                <i class="fas fa-dumbbell text-white"></i>
            </div>
            <span class="text-white font-bold text-xl">RawFit Admin</span>
        </div>
        <div class="flex items-center space-x-6">
            <a href="admin.php" class="text-gray-300 hover:text-white">Dashboard</a>
            <a href="admin_gyms.php" class="text-orange-400 font-medium">Gyms</a>
            <a href="?action=logout" class="text-gray-300 hover:text-white">Logout</a>
        </div>
    </div>
</nav>

<main class="pt-24 pb-12 px-4 max-w-7xl mx-auto">

    <!-- ALERT -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="glass rounded-xl p-4 mb-6 border-l-4 <?= stripos($_SESSION['msg'],'success')!==false || stripos($_SESSION['msg'],'approved')!==false ? 'border-green-500 bg-green-900/30' : 'border-red-500 bg-red-900/30' ?>">
            <p class="text-sm flex items-center gap-2">
                <i class="fas <?= stripos($_SESSION['msg'],'success')!==false || stripos($_SESSION['msg'],'approved')!==false ? 'fa-check-circle text-green-400' : 'fa-exclamation-circle text-red-400' ?>"></i>
                <?= $_SESSION['msg'] ?>
            </p>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <!-- SEARCH -->
    <form method="GET" class="mb-8">
        <div class="relative max-w-xl mx-auto">
            <input type="text" name="search" placeholder="Search gym, email, location…" value="<?= htmlspecialchars($search) ?>"
                   class="w-full pl-12 pr-4 py-4 bg-gray-800/80 border border-gray-700 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-orange-500 hover:bg-orange-600 text-white px-5 py-2 rounded-lg font-semibold">Search</button>
        </div>
    </form>

    <!-- TABLE -->
    <div class="glass rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-orange-500 to-red-600 text-white">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase">Gym</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase">Owner</th>
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase">Location</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <?php foreach ($gyms as $gym): ?>
                        <?php
                        $status = $gym['status'] ?? 0;
                        $statusText = $status == 1 ? 'approved' : ($status == 2 ? 'rejected' : 'pending');
                        $badgeClass = "badge-$statusText";
                        $gymJson = htmlspecialchars(json_encode($gym), ENT_QUOTES);
                        ?>
                        <tr class="hover:bg-gray-800/50 transition cursor-pointer" onclick="openDetails(<?= $gymJson ?>)">
                            <td class="px-4 py-3 text-sm">#<?= $gym['gym_id'] ?></td>
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars($gym['gym_name'] ?? '') ?></td>
                            <td class="px-4 py-3 text-sm"><?= htmlspecialchars($gym['owner_name'] ?? '—') ?></td>
                            <td class="px-4 py-3 text-sm text-gray-300"><?= htmlspecialchars($gym['location'] ?? '') ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst($statusText) ?></span>
                            </td>
                            <td class="px-4 py-3 text-center" onclick="event.stopPropagation();">
                                <div class="flex justify-center gap-2">
                                    <?php if ($status == 0): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Approve this gym?');">
                                            <input type="hidden" name="gym_id" value="<?= $gym['gym_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded flex items-center gap-1">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Reject this gym?');">
                                            <input type="hidden" name="gym_id" value="<?= $gym['gym_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button type="submit" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded flex items-center gap-1">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete permanently?');">
                                        <input type="hidden" name="delete_gym" value="<?= $gym['gym_id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="submit" class="px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white text-xs rounded flex items-center gap-1">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($gyms)): ?>
                        <tr><td colspan="6" class="text-center py-8 text-gray-500">No gyms found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- GYM DETAILS MODAL -->
<div id="gymModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="glass max-w-4xl w-full max-h-screen overflow-y-auto rounded-2xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 id="modalTitle" class="text-2xl font-bold text-orange-400"></h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-semibold text-white mb-3">Images</h3>
                <div id="imageGallery" class="grid grid-cols-2 gap-3"></div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-white mb-3">Gym Details</h3>
                <div class="space-y-3 text-sm">
                    <div><strong>Owner:</strong> <span id="ownerName"></span></div>
                    <div><strong>Email:</strong> <span id="gymEmail"></span></div>
                    <div><strong>Phone:</strong> <span id="gymPhone"></span></div>
                    <div><strong>Address:</strong> <span id="fullAddress"></span></div>
                    <div><strong>Timings:</strong> <span id="timings"></span></div>
                    <div><strong>Facilities:</strong> <span id="facilities"></span></div>
                    <div><strong>Capacity:</strong> <span id="capacity"></span></div>
                    <div><strong>Trainers:</strong> <span id="numTrainers"></span></div>
                    <div><strong>Experience:</strong> <span id="experienceYears"></span></div>
                    <div><strong>Registered:</strong> <span id="registrations"></span></div>
                    <div><strong>Description:</strong></div>
                    <p id="description" class="text-gray-300 mt-1"></p>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button onclick="closeModal()" class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg">Close</button>
        </div>
    </div>
</div>

<!-- FULL-SCREEN LIGHTBOX -->
<div id="lightbox" class="fixed inset-0 bg-black/95 hidden z-[60] flex items-center justify-center p-4 lightbox">
    <button id="prevBtn" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-orange-400 transition z-10">
        <i class="fas fa-chevron-left"></i>
    </button>
    <button id="nextBtn" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-4xl hover:text-orange-400 transition z-10">
        <i class="fas fa-chevron-right"></i>
    </button>
    <button id="closeLightbox" class="absolute top-6 right-6 text-white text-3xl hover:text-red-400 transition z-10">
        <i class="fas fa-times"></i>
    </button>
    <img id="lightboxImg" src="" alt="" class="max-w-full max-h-full object-contain">
</div>

<script>
let currentImages = [];
let currentIndex = 0;

function openDetails(gym) {
    document.getElementById('modalTitle').textContent = gym.gym_name || 'Unknown Gym';
    document.getElementById('ownerName').textContent = gym.owner_name || '—';
    document.getElementById('gymEmail').textContent = gym.gym_email || '—';
    document.getElementById('gymPhone').textContent = gym.gym_phone || '—';
    document.getElementById('fullAddress').textContent = 
        [gym.gym_address, gym.location, gym.gym_city, gym.gym_state, gym.gym_zip].filter(Boolean).join(', ') || '—';
    document.getElementById('timings').textContent = gym.timings || '—';
    document.getElementById('facilities').textContent = gym.facilities || '—';
    document.getElementById('capacity').textContent = gym.capacity || '0';
    document.getElementById('numTrainers').textContent = gym.num_trainers || '0';
    document.getElementById('experienceYears').textContent = (gym.experience_years || '0') + ' years';
    document.getElementById('registrations').textContent = gym.registrations || '0';
    document.getElementById('description').textContent = gym.gym_description || 'No description.';

    const gallery = document.getElementById('imageGallery');
    gallery.innerHTML = '';
    currentImages = gym.images || [];

    if (currentImages.length === 0) {
        gallery.innerHTML = '<p class="text-gray-500 col-span-2">No images uploaded.</p>';
    } else {
        currentImages.forEach((img, idx) => {
            const div = document.createElement('div');
            div.className = 'relative group cursor-pointer';
            div.innerHTML = `
                <img src="pari/uploads/gyms/${img}" class="w-full h-32 object-cover rounded-lg border border-gray-600" 
                     onerror="this.src='https://via.placeholder.com/300x200?text=No+Image'; this.onerror=null;"
                     onclick="openLightbox(${idx})">
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition rounded-lg flex items-center justify-center">
                    <i class="fas fa-search-plus text-white text-xl"></i>
                </div>`;
            gallery.appendChild(div);
        });
    }

    document.getElementById('gymModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('gymModal').classList.add('hidden');
}

// === LIGHTBOX ===
function openLightbox(index) {
    currentIndex = index;
    const img = document.getElementById('lightboxImg');
    img.src = 'pari/uploads/gyms/' + currentImages[currentIndex];
    document.getElementById('lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

document.getElementById('prevBtn').onclick = () => {
    currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
    document.getElementById('lightboxImg').src = 'pari/uploads/gyms/' + currentImages[currentIndex];
};

document.getElementById('nextBtn').onclick = () => {
    currentIndex = (currentIndex + 1) % currentImages.length;
    document.getElementById('lightboxImg').src = 'pari/uploads/gyms/' + currentImages[currentIndex];
};

document.getElementById('closeLightbox').onclick = closeLightbox;

// Close on backdrop
document.getElementById('lightbox').addEventListener('click', e => {
    if (e.target.id === 'lightbox') closeLightbox();
});

// Keyboard navigation
document.addEventListener('keydown', e => {
    if (document.getElementById('lightbox').classList.contains('hidden')) return;
    if (e.key === 'ArrowLeft') document.getElementById('prevBtn').click();
    if (e.key === 'ArrowRight') document.getElementById('nextBtn').click();
    if (e.key === 'Escape') closeLightbox();
});

// Close modal on backdrop
document.getElementById('gymModal').addEventListener('click', e => {
    if (e.target.id === 'gymModal') closeModal();
});
</script>

</body>
</html>
<?php $conn->close(); ?>