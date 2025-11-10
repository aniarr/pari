const API_URL = 'api/fetch_exercises.php';
let splitData = Array(7).fill().map(() => []);

// SEARCH
document.getElementById('searchBtn').onclick = search;
document.getElementById('searchInput').addEventListener('keypress', e => e.key === 'Enter' && search());
document.getElementById('categoryFilter').addEventListener('change', search);

async function search() {
    const q = document.getElementById('searchInput').value.trim();
    const cat = document.getElementById('categoryFilter').value;
    if (!q && !cat) return;

    const btn = document.getElementById('searchBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';

    const params = new URLSearchParams();
    if (q) params.append('q', q);
    if (cat) params.append('category', cat);

    try {
        const res = await fetch(`${API_URL}?${params}`);
        const data = await res.json();

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Search';

        const container = document.getElementById('exerciseResults');
        container.innerHTML = '';

        if (!data.exercises || data.exercises.length === 0) {
            container.innerHTML = '<p class="col-span-full text-center text-gray-500">No exercises found. Try "pushup", "squat", or select a category.</p>';
            return;
        }

        data.exercises.forEach(ex => {
            const card = document.createElement('div');
            card.className = 'border rounded-xl overflow-hidden shadow hover:shadow-xl transition cursor-move';
            card.draggable = true;
            card.dataset.exercise = JSON.stringify(ex);

            const imgUrl = ex.gifUrl ? `https://wger.de${ex.gifUrl}` : 'https://via.placeholder.com/200x150?text=No+Image';

            card.innerHTML = `
                <img src="${imgUrl}" alt="${ex.name}" class="w-full h-40 object-cover" 
                     onerror="this.src='https://via.placeholder.com/200x150?text=No+Image'"/>
                <div class="p-3">
                    <h4 class="font-bold text-sm truncate">${ex.name}</h4>
                    <p class="text-xs text-gray-600">${ex.target || 'Multiple'} • ${ex.bodyPart}</p>
                </div>
            `;
            card.addEventListener('click', () => showModal(ex));
            card.addEventListener('dragstart', drag);
            container.appendChild(card);
        });
    } catch (err) {
        console.error(err);
        alert('Network error');
    }
}

// DRAG & DROP
function allowDrop(e) { e.preventDefault(); }
function drag(e) {
    const el = e.target.closest('[data-exercise]');
    const ex = JSON.parse(el.dataset.exercise);
    e.dataTransfer.setData('exercise', JSON.stringify(ex));
}
function dragEnter(e) { e.target.classList.add('dragover'); }
function dragLeave(e) { e.target.classList.remove('dragover'); }

function drop(e) {
    e.preventDefault();
    e.target.classList.remove('dragover');
    const ex = JSON.parse(e.dataTransfer.getData('exercise'));
    const dayCard = e.target.closest('.day-card');
    const dayIdx = parseInt(dayCard.dataset.day) - 1;

    if (!splitData[dayIdx].find(i => i.id === ex.id)) {
        splitData[dayIdx].push({ ...ex, sets: 3, reps: '8-12', rest: 60 });
        renderDay(dayIdx);
        updateCount();
    }
}

function renderDay(idx) {
    const day = document.querySelectorAll('.day-card')[idx];
    const zone = day.querySelector('.exercise-dropzone');
    const list = splitData[idx];

    if (!list.length) {
        zone.innerHTML = `<i class="fas fa-dumbbell text-3xl mb-2"></i><p>Drop exercises here</p>`;
        return;
    }

    zone.innerHTML = list.map((ex, i) => `
        <div class="bg-indigo-50 p-3 rounded-lg mb-2 flex justify-between items-center border border-indigo-200">
            <div>
                <p class="font-medium text-sm">${ex.name}</p>
                <p class="text-xs text-gray-600">${ex.sets}×${ex.reps} | Rest: ${ex.rest}s</p>
            </div>
            <button onclick="remove(${idx},${i})" class="text-red-500 hover:text-red-700">
                <i class="fas fa-trash text-sm"></i>
            </button>
        </div>
    `).join('');
}
function remove(day, i) {
    splitData[day].splice(i, 1);
    renderDay(day);
    updateCount();
}
function updateCount() {
    const total = splitData.flat().length;
    document.getElementById('selectedCount').textContent = total;
    document.getElementById('selectedExercises').classList.toggle('hidden', total === 0);
}

// SAVE
document.getElementById('saveSplit').onclick = async () => {
    const name = document.getElementById('splitName').value.trim() || 'My Split';
    const payload = { name, days: splitData.filter(d => d.length) };
    if (!payload.days.length) return alert('Add at least one exercise!');

    const res = await fetch('save_split.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const r = await res.json();
    if (r.success) {
        alert('Saved! ID: ' + r.splitId);
        location.href = `view_split.php?id=${r.splitId}`;
    } else {
        alert('Error: ' + r.error);
    }
};

// MODAL
function showModal(ex) {
    const modal = document.getElementById('exerciseModal');
    const content = document.getElementById('modalContent');
    const img = ex.gifUrl ? `<img src="https://wger.de${ex.gifUrl}" class="w-full max-w-md mx-auto rounded-xl shadow"/>` : '';
    const desc = ex.instructions ? `<p class="whitespace-pre-wrap text-sm">${ex.instructions}</p>` : '<p class="text-gray-500">No description.</p>';

    content.innerHTML = `
        <div class="text-center mb-6">${img}</div>
        <h2 class="text-3xl font-bold text-indigo-700 mb-4">${ex.name}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-6">
            <div class="bg-gray-100 p-3 rounded-lg"><strong>Category:</strong> ${ex.bodyPart}</div>
            <div class="bg-gray-100 p-3 rounded-lg"><strong>Muscles:</strong> ${ex.target || 'Multiple'}</div>
            <div class="bg-gray-100 p-3 rounded-lg"><strong>Equipment:</strong> ${ex.equipment || 'Bodyweight'}</div>
        </div>
        <div><h3 class="text-xl font-semibold mb-3">Description:</h3>${desc}</div>
    `;
    modal.classList.remove('hidden');
}
function closeModal() { document.getElementById('exerciseModal').classList.add('hidden'); }

// INIT
document.querySelectorAll('.day-card').forEach((c, i) => {
    c.dataset.day = i + 1;
    renderDay(i);
});