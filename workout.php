<?php require 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RawFit - Workout Split Builder</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
  <style>
    body{font-family:'Inter',sans-serif;}
    .glass{background:rgba(255,255,255,0.08);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.1);}
    .btn-orange{background:linear-gradient(to right,#f97316,#ea580c);}
    .btn-orange:hover{background:linear-gradient(to right,#ea580c,#dc2626);}
    .dropzone.dragover{border-color:#f97316;background:rgba(251,146,60,0.1);}
    .page-btn{@apply px-4 py-2 rounded-lg bg-gray-700/50 text-white hover:bg-orange-600 transition text-sm font-medium;}
    .page-btn.active{@apply bg-orange-600;}
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white min-h-screen">

<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "rawfit");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get logged-in user's name
$user_id = $_SESSION['user_id'];
$sql = "SELECT name FROM register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$userName = "User"; // Default name
if ($row = $result->fetch_assoc()) {
    $userName = $row['name'];
}

$stmt->close();
$conn->close();
?>

<!-- Navigation -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-black/90 backdrop-blur-md border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-gradient-to-r from-orange-500 to-red-500 rounded-lg flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M6.5 6.5h11v11h-11z"/>
                        <path d="M6.5 6.5L2 2"/>
                        <path d="M17.5 6.5L22 2"/>
                        <path d="M6.5 17.5L2 22"/>
                        <path d="M17.5 17.5L22 22"/>
                    </svg>
                </div>
                <span class="text-white font-bold text-xl">RawFit</span>
            </div>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="home.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    <span>Home</span>
                </a>
                <a href="nutrition.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Nutrition</span>
                </a>
                <a href="trainer.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Trainers</span>
                </a>
                <a href="display_gym.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                        <path d="M12 18h.01"/>
                    </svg>
                    <span>Gyms</span>
                </a>
                  <a href="workout_view.php" class="nav-link flex items-center space-x-2 px-3 py-2 rounded-lg text-gray-300 hover:text-white hover:bg-gray-800 transition-colors">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                            <path d="M12 18h.01"/>
                        </svg>
                        <span>Workout</span>
                    </a> 
            </div>

            <!-- User Info -->
            <div class="relative flex items-center space-x-4">
                <div class="hidden sm:block text-right">
                    <p class="text-white font-medium" id="userName"><?php echo htmlspecialchars($userName); ?></p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-orange-500 to-red-500 rounded-full flex items-center justify-center cursor-pointer" id="profileButton">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-white">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>

                <!-- Dropdown Menu -->
                <div id="profileDropdown" class="absolute top-full right-0 mt-2 w-48 bg-gray-800/90 backdrop-blur-md border border-gray-700 rounded-lg shadow-lg hidden z-50">
                    <a href="profile.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">View Profile</a>
                    <a href="logout.php" class="block px-4 py-2 text-white hover:bg-gray-700 transition-colors">Logout</a>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden flex items-center justify-around py-3 border-t border-gray-800">
            <a href="home.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-orange-500">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                <span class="text-xs">Home</span>
            </a>
            <a href="nutrition.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2"/>
                    <path d="M12 18h.01"/>
                </svg>
                <span class="text-xs">Nutrition</span>
            </a>
            <a href="trainer.php" class="mobile-nav-link flex flex-col items-center space-y-1 px-3 py-2 rounded-lg text-gray-400">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span class="text-xs">Trainers</span>
            </a>
        </div>
    </div>
</nav>

<script>
      document.addEventListener('DOMContentLoaded', function() {
          updateNavigation();
      });

      // Highlight active link
      function updateNavigation() {
          const currentPage = window.location.pathname.split('/').pop();
          const navLinks = document.querySelectorAll('.nav-link');
          const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

          [...navLinks, ...mobileNavLinks].forEach(link => {
              link.classList.remove('active', 'bg-orange-500', 'text-white', 'text-orange-500');
              link.classList.add('text-gray-300', 'hover:text-white', 'hover:bg-gray-800');
          });

          if (currentPage === 'index.php' || currentPage === 'home.php' || currentPage === '') {
              const homeLinks = document.querySelectorAll('a[href="home.php"], a[href="index.php"]');
              homeLinks.forEach(link => {
                  if (link.classList.contains('mobile-nav-link')) {
                      link.classList.add('active', 'text-orange-500');
                      link.classList.remove('text-gray-400');
                  } else {
                      link.classList.add('active', 'bg-orange-500', 'text-white');
                      link.classList.remove('text-gray-300');
                  }
              });
          }
      }

      // Profile dropdown toggle
      const profileButton = document.getElementById('profileButton');
      const profileDropdown = document.getElementById('profileDropdown');

      if (profileButton && profileDropdown) {
          profileButton.addEventListener('click', function(e) {
              e.preventDefault();
              profileDropdown.classList.toggle('hidden');
          });

          document.addEventListener('click', function(e) {
              if (!profileButton.contains(e.target) && !profileDropdown.contains(e.target)) {
                  profileDropdown.classList.add('hidden');
              }
          });
      }
</script>
<br><br>

<div class="container mx-auto px-4 py-12 max-w-7xl">

  <div class="text-center mb-10">
    <h1 class="text-5xl md:text-6xl font-bold bg-gradient-to-r from-orange-400 to-red-600 bg-clip-text text-transparent">
      Workout Split Builder
    </h1>
  </div>

  <!-- Search -->
  <div class="glass p-8 rounded-2xl shadow-xl mb-8 border border-gray-700">
    <div class="flex gap-3">
      <input type="text" id="searchInput" placeholder="Search 800+ exercises..."
             class="flex-1 px-5 py-4 bg-gray-800/50 border border-gray-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 transition text-lg"/>
      <button id="searchBtn" class="btn-orange text-white px-8 py-4 rounded-xl font-medium flex items-center gap-2 shadow-lg hover:shadow-orange-500/25 transition transform hover:scale-105">
        Search
      </button>
    </div>
    <div id="exerciseResults" class="mt-8 space-y-3"></div>
    <div id="pagination" class="flex justify-center gap-2 mt-6 hidden"></div>
  </div>

  <!-- Selected -->
  <div id="selectedExercises" class="glass p-6 rounded-2xl shadow-xl mb-8 hidden border border-gray-700">
    <h3 class="text-xl font-bold text-orange-400 mb-4">Selected (<span id="selectedCount">0</span>)</h3>
    <div id="selectedList" class="space-y-2"></div>
  </div>

  <!-- 7-Day Split -->
  <div class="glass p-8 rounded-2xl shadow-xl border border-gray-700">
    <input type="text" id="splitName" placeholder="Enter Split Name (e.g., Push Pull Legs)"
           class="w-full px-5 py-4 bg-gray-800/50 border border-gray-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 mb-8 text-lg"/>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <?php
      $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
      foreach ($days as $i => $day):
      ?>
      <div class="day-card bg-gray-800/60 border border-gray-600 rounded-2xl p-5 shadow-lg hover:shadow-orange-500/20 transition-all duration-300 transform hover:-translate-y-1"
           data-day="<?= $i+1 ?>">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-bold text-white"><?= $day ?></h3>
          <span class="text-xs font-medium text-orange-400 bg-orange-400/10 px-2 py-1 rounded-full">Day <?= $i+1 ?></span>
        </div>

        <div class="exercise-dropzone min-h-48 p-5 border-2 border-dashed border-gray-600 rounded-xl bg-gray-900/40 flex flex-col items-center justify-center text-gray-400 transition-all duration-200 hover:border-orange-500 hover:bg-orange-500/5"
             ondrop="drop(event)" ondragover="allowDrop(event)" ondragenter="dragEnter(event)" ondragleave="dragLeave(event)">
          <p class="text-sm font-medium">Drop or add exercises here</p>
        </div>

        <div class="exercise-list mt-4 space-y-2"></div>
      </div>
      <?php endforeach; ?>
    </div>

   <button id="saveSplit" class="mt-10 w-full btn-orange text-white py-4 rounded-xl text-xl font-bold shadow-lg hover:shadow-orange-500/25 transition transform hover:scale-105">
  Save
</button>
  </div>

  <!-- Modal -->
  <div id="exerciseModal" class="fixed inset-0 bg-black bg-opacity-70 hidden flex items-center justify-center z-50 p-4">
    <div class="glass rounded-2xl max-w-3xl w-full max-h-screen overflow-y-auto p-8 relative shadow-2xl border border-gray-700">
      <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white text-3xl">X</button>
      <div id="modalContent"></div>
    </div>
  </div>

</div>

<script>
/* ---------- STATE ---------- */
const splitData = Array(7).fill().map(() => []);
let currentPage = 1;
const PER_PAGE = 10;

/* ---------- PROMPT FOR REPS/REST ---------- */
function promptExerciseDetails(ex) {
  return new Promise(resolve => {
    // Build a tiny modal (no external lib)
    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4';
    overlay.innerHTML = `
      <div class="glass rounded-2xl p-6 max-w-md w-full border border-gray-700">
        <h3 class="text-xl font-bold text-orange-400 mb-4">Add <span class="text-white">${ex.name}</span></h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm text-gray-300 mb-1">Sets</label>
            <input type="number" id="inpSets" min="1" value="3"
                   class="w-full px-3 py-2 bg-gray-800/50 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div>
            <label class="block text-sm text-gray-300 mb-1">Reps (e.g. 8-12)</label>
            <input type="text" id="inpReps" value="8-12"
                   class="w-full px-3 py-2 bg-gray-800/50 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div>
            <label class="block text-sm text-gray-300 mb-1">Rest (seconds)</label>
            <input type="number" id="inpRest" min="0" value="60"
                   class="w-full px-3 py-2 bg-gray-800/50 border border-gray-600 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button id="cancelDetail" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-2 rounded-lg transition">Cancel</button>
          <button id="confirmDetail" class="flex-1 btn-orange text-white py-2 rounded-lg transition">Add</button>
        </div>
      </div>
    `;
    document.body.appendChild(overlay);

    const sets = overlay.querySelector('#inpSets');
    const reps = overlay.querySelector('#inpReps');
    const rest = overlay.querySelector('#inpRest');

    overlay.querySelector('#cancelDetail').onclick = () => {
      overlay.remove(); resolve(null);
    };
    overlay.querySelector('#confirmDetail').onclick = () => {
      const s = parseInt(sets.value) || 3;
      const r = reps.value.trim() || '8-12';
      const t = parseInt(rest.value) || 60;
      overlay.remove();
      resolve({sets: s, reps: r, rest: t});
    };
  });
}

/* ---------- LOAD TEMP ---------- */
async function loadTemp(){
  const res = await fetch('get_temp.php');
  const data = await res.json();
  data.forEach((day, idx)=>{ splitData[idx]=day; renderDay(idx); });
  updateCount();
}
loadTemp();

/* ---------- SEARCH ---------- */
document.getElementById('searchBtn').onclick = search;
document.getElementById('searchInput').addEventListener('keypress',e=>e.key==='Enter'&&search());

async function search(){
  const q = document.getElementById('searchInput').value.trim();
  currentPage = 1;
  const btn = document.getElementById('searchBtn');
  btn.disabled = true; btn.innerHTML = 'Searching...';

  const res = await fetch(`search_exercises.php?q=${encodeURIComponent(q)}`);
  const {exercises} = await res.json();

  btn.disabled = false; btn.innerHTML = 'Search';

  const container = document.getElementById('exerciseResults');
  const pagination = document.getElementById('pagination');
  container.innerHTML=''; pagination.innerHTML='';

  if(!exercises.length){
    container.innerHTML='<p class="text-center text-gray-400 py-12 text-lg">No exercises found.</p>';
    pagination.classList.add('hidden');
    return;
  }

  const totalPages = Math.ceil(exercises.length/PER_PAGE);
  const start = (currentPage-1)*PER_PAGE;
  const page = exercises.slice(start,start+PER_PAGE);

  page.forEach(ex=>{
    const row = document.createElement('div');
    row.className='flex items-center justify-between bg-gray-800/50 p-4 rounded-lg border border-gray-600 hover:border-orange-500 transition';
    row.draggable=true;
    row.dataset.exercise=JSON.stringify(ex);

    const info = document.createElement('div');
    info.className='flex-1 min-w-0 cursor-pointer';
    info.innerHTML=`<p class="font-medium text-white truncate">${ex.name}</p>
                    <p class="text-xs text-orange-400">${ex.target} • ${ex.bodyPart}</p>`;
    info.onclick=()=>showModal(ex);

    const controls = document.createElement('div');
    controls.className='flex items-center gap-2';
    const sel = document.createElement('select');
    sel.className='bg-gray-700 text-white text-xs rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-orange-500';
    sel.innerHTML=`<option value="" disabled selected>Day</option>`+
      ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']
        .map((d,i)=>`<option value="${i}">${d}</option>`).join('');
    const add = document.createElement('button');
    add.className='bg-orange-600 hover:bg-orange-400 text-white px-3 py-1 rounded text-xs font-medium transition';
    add.textContent='Add';
    add.onclick=async ()=> {
      const dayIdx = parseInt(sel.value);
      if (isNaN(dayIdx)) return alert('Select a day');
      await addExerciseToDay(ex, dayIdx);
    };

    controls.append(sel,add);
    row.append(info,controls);
    container.appendChild(row);
    row.addEventListener('dragstart',drag);
  });

  if(totalPages>1){
    pagination.classList.remove('hidden');
    for(let i=1;i<=totalPages;i++){
      const b=document.createElement('button');
      b.className=`page-btn ${i===currentPage?'active':''}`;
      b.textContent=i;
      b.onclick=()=>{currentPage=i;search();};
      pagination.appendChild(b);
    }
  }else pagination.classList.add('hidden');
}

/* ---------- ADD (UI + DB) ---------- */
async function addExerciseToDay(ex, dayIdx){
  // 1. Ask user
  const details = await promptExerciseDetails(ex);
  if (!details) return;                 // cancelled

  // 2. Prevent duplicate in the same day
  if (splitData[dayIdx].some(e=>e.id===ex.id)) {
    return alert('Already added to this day');
  }

  // 3. Store with custom values
  splitData[dayIdx].push({...ex, ...details});
  renderDay(dayIdx);
  updateCount();

  // 4. Persist temporary data
  await fetch('add_exercise.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({exercise:ex, day:dayIdx, ...details})
  });
}

/* ---------- DRAG & DROP ---------- */
function allowDrop(e){e.preventDefault();}
function drag(e){
  const ex = JSON.parse(e.target.closest('[data-exercise]').dataset.exercise);
  e.dataTransfer.setData('exercise',JSON.stringify(ex));
}
function dragEnter(e){e.target.classList.add('dragover');}
function dragLeave(e){e.target.classList.remove('dragover');}
function drop(e){
  e.preventDefault();
  e.target.classList.remove('dragover');
  const ex = JSON.parse(e.dataTransfer.getData('exercise'));
  const dayIdx = parseInt(e.target.closest('.day-card').dataset.day)-1;
  addExerciseToDay(ex,dayIdx);
}

/* ---------- RENDER DAY ---------- */
function renderDay(idx){
  const card = document.querySelectorAll('.day-card')[idx];
  const zone = card.querySelector('.exercise-dropzone');
  const list = card.querySelector('.exercise-list');
  const day = splitData[idx];

  if(!day.length){
    zone.innerHTML=`<p class="text-sm font-medium">Drop or add exercises here</p>`;
    list.innerHTML='';
    return;
  }
  zone.innerHTML=`<p class="text-xs text-gray-500 text-center">Add more...</p>`;
  list.innerHTML = day.map((ex,i)=>`
    <div class="bg-gray-700/50 p-3 rounded-lg flex justify-between items-center border border-gray-600 hover:border-orange-500 transition">
      <div class="flex-1 min-w-0">
        <p class="font-medium text-white text-sm truncate">${ex.name}</p>
        <p class="text-xs text-orange-400">${ex.sets}×${ex.reps} | Rest: ${ex.rest}s</p>
      </div>
      <button onclick="remove(${idx},${i})" class="text-red-400 hover:text-red-300 ml-2 transition">X</button>
    </div>
  `).join('');
}
function remove(day,i){
  splitData[day].splice(i,1);
  renderDay(day);
  updateCount();
}
function updateCount(){
  const total = splitData.flat().length;
  document.getElementById('selectedCount').textContent = total;
  document.getElementById('selectedExercises').classList.toggle('hidden',total===0);
}

/* ---------- SAVE SPLIT ---------- */
document.getElementById('saveSplit').onclick = async () => {
  const name = document.getElementById('splitName').value.trim() || 'My Split';
  const payload = {name, days: splitData.filter(d=>d.length)};
  if(!payload.days.length) return alert('Add at least one exercise!');

  const res = await fetch('save_split.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(payload)
  });
  const r = await res.json();
  if(r.success){
    alert('Saved! ID: '+r.splitId);
    location.href = `view_split.php?id=${r.splitId}`;
  }else{
    alert('Error: '+r.error);
  }
};

/* ---------- MODAL ---------- */
function showModal(ex){
  const modal = document.getElementById('exerciseModal');
  const content = document.getElementById('modalContent');
  content.innerHTML = `
    <div class="text-center mb-6">
     
    </div>
    <h2 class="text-3xl font-bold text-orange-400 mb-4">${ex.name}</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-6">
      <div class="bg-gray-800/50 p-4 rounded-lg border border-gray-600"><strong class="text-orange-400">Target:</strong> <span class="text-white">${ex.target||'-'}</span></div>
      <div class="bg-gray-800/50 p-4 rounded-lg border border-gray-600"><strong class="text-orange-400">Body Part:</strong> <span class="text-white">${ex.bodyPart||'-'}</span></div>
      <div class="bg-gray-800/50 p-4 rounded-lg border border-gray-600"><strong class="text-orange-400">Equipment:</strong> <span class="text-white">${ex.equipment||'-'}</span></div>
    </div>
    <div class="bg-gray-800/50 p-5 rounded-lg border border-gray-600">
      <h3 class="text-xl font-semibold text-orange-400 mb-3">Instructions</h3>
      <p class="text-gray-300 leading-relaxed">${(ex.instructions||'').replace(/\n/g,'<br>')}</p>
    </div>
  `;
  modal.classList.remove('hidden');
}
function closeModal(){ document.getElementById('exerciseModal').classList.add('hidden'); }

/* ---------- INIT ---------- */
document.querySelectorAll('.day-card').forEach((c,i)=>{ c.dataset.day=i+1; renderDay(i); });
</script>

</body>
</html>