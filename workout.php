<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/vite.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Workout Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
  font-family: Inter, system-ui, Avenir, Helvetica, Arial, sans-serif;
  line-height: 1.5;
  font-weight: 400;

  color-scheme: light dark;
  color: rgba(255, 255, 255, 0.87);
  background-color: #242424;

  font-synthesis: none;
  text-rendering: optimizeLegibility;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a {
  font-weight: 500;
  color: #646cff;
  text-decoration: inherit;
}
a:hover {
  color: #535bf2;
}

body {
  margin: 0;
  display: flex;
  place-items: center;
  min-width: 320px;
  min-height: 100vh;
}

h1 {
  font-size: 3.2em;
  line-height: 1.1;
}

#app {
  max-width: 1280px;
  margin: 0 auto;
  padding: 2rem;
  text-align: center;
}

.logo {
  height: 6em;
  padding: 1.5em;
  will-change: filter;
  transition: filter 300ms;
}
.logo:hover {
  filter: drop-shadow(0 0 2em #646cffaa);
}
.logo.vanilla:hover {
  filter: drop-shadow(0 0 2em #f7df1eaa);
}

.card {
  padding: 2em;
}

.read-the-docs {
  color: #888;
}

button {
  border-radius: 8px;
  border: 1px solid transparent;
  padding: 0.6em 1.2em;
  font-size: 1em;
  font-weight: 500;
  font-family: inherit;
  background-color: #1a1a1a;
  cursor: pointer;
  transition: border-color 0.25s;
}
button:hover {
  border-color: #646cff;
}
button:focus,
button:focus-visible {
  outline: 4px auto -webkit-focus-ring-color;
}

@media (prefers-color-scheme: light) {
  :root {
    color: #213547;
    background-color: #ffffff;
  }
  a:hover {
    color: #747bff;
  }
  button {
    background-color: #f9f9f9;
  }
}

    </style>
  </head>
  <body class="bg-gray-50">
    <div id="app" class="min-h-screen">
      <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div class="flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">Workout Tracker</h1>
            <button id="newWorkoutBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg transition">
              + New Workout
            </button>
          </div>
        </div>
      </header>

      <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="workoutsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        </div>

        <div id="emptyState" class="text-center py-20 hidden">
          <svg class="mx-auto h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
          <h3 class="mt-4 text-xl font-medium text-gray-900">No workouts yet</h3>
          <p class="mt-2 text-gray-500">Get started by creating your first workout split</p>
        </div>
      </main>
    </div>

    <div id="workoutModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
          <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Create New Workout</h2>
          <button id="closeModal" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">&times;</button>
        </div>

        <div class="p-6">
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Workout Name</label>
            <input type="text" id="workoutName" placeholder="e.g., Push Day, Leg Day" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
            <textarea id="workoutDescription" placeholder="Optional workout description" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
          </div>

          <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold text-gray-900">Exercises</h3>
              <button id="addExerciseBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition">+ Add Exercise</button>
            </div>
            <div id="exercisesList" class="space-y-3">
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button id="cancelBtn" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Cancel</button>
            <button id="saveWorkoutBtn" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Save Workout</button>
          </div>
        </div>
      </div>
    </div>

    <div id="exerciseModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
          <h2 class="text-2xl font-bold text-gray-900">Add Exercise</h2>
          <button id="closeExerciseModal" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">&times;</button>
        </div>

        <div class="p-6">
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Category</label>
            <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="">All Categories</option>
              <option value="chest">Chest</option>
              <option value="back">Back</option>
              <option value="legs">Legs</option>
              <option value="shoulders">Shoulders</option>
              <option value="arms">Arms</option>
              <option value="core">Core</option>
              <option value="cardio">Cardio</option>
            </select>
          </div>

          <div id="exerciseSelectionList" class="space-y-2 max-h-96 overflow-y-auto">
          </div>
        </div>
      </div>
    </div>

    <div id="viewWorkoutModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center">
          <div>
            <h2 id="viewWorkoutTitle" class="text-2xl font-bold text-gray-900"></h2>
            <p id="viewWorkoutDescription" class="text-sm text-gray-600 mt-1"></p>
          </div>
          <button id="closeViewModal" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">&times;</button>
        </div>

        <div class="p-6">
          <div id="viewExercisesList" class="space-y-4">
          </div>
        </div>
      </div>
    </div>

    <script type="module" src="/workout.js"></script>
  </body>
  <script>
    const SUPABASE_URL = 'https://vghawarveydrwtsxvqkj.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZnaGF3YXJ2ZXlkcnd0c3h2cWtqIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjI1OTcwMzgsImV4cCI6MjA3ODE3MzAzOH0.53-d9txfszFVwN2tim9zjFw_9eYsNpKTfcSuXuXH8ew';

let allExercises = [];
let currentWorkoutExercises = [];
let currentSplitId = null;

async function supabaseRequest(endpoint, method = 'GET', data = null) {
  const url = `${SUPABASE_URL}/rest/v1/${endpoint}`;

  const options = {
    method,
    headers: {
      'apikey': SUPABASE_ANON_KEY,
      'Authorization': `Bearer ${SUPABASE_ANON_KEY}`,
      'Content-Type': 'application/json'
    }
  };

  if (data && (method === 'POST' || method === 'PUT')) {
    options.body = JSON.stringify(data);
  }

  const response = await fetch(url, options);
  return response.json();
}

async function loadWorkouts() {
  const splits = await supabaseRequest('workout_splits?select=*&order=created_at.desc');
  const workoutsList = document.getElementById('workoutsList');
  const emptyState = document.getElementById('emptyState');

  if (!splits || splits.length === 0) {
    workoutsList.innerHTML = '';
    emptyState.classList.remove('hidden');
    return;
  }

  emptyState.classList.add('hidden');
  workoutsList.innerHTML = splits.map(split => `
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition cursor-pointer" data-split-id="${split.id}">
      <h3 class="text-xl font-bold text-gray-900 mb-2">${split.name}</h3>
      <p class="text-gray-600 text-sm mb-4">${split.description || 'No description'}</p>
      <div class="flex justify-between items-center">
        <button class="view-workout text-blue-600 hover:text-blue-700 font-medium text-sm">View Details</button>
        <button class="delete-workout text-red-600 hover:text-red-700 font-medium text-sm">Delete</button>
      </div>
    </div>
  `).join('');

  document.querySelectorAll('.view-workout').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const splitId = e.target.closest('[data-split-id]').dataset.splitId;
      viewWorkout(splitId);
    });
  });

  document.querySelectorAll('.delete-workout').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const splitId = e.target.closest('[data-split-id]').dataset.splitId;
      deleteWorkout(splitId);
    });
  });
}

async function viewWorkout(splitId) {
  const split = await supabaseRequest(`workout_splits?id=eq.${splitId}&select=*`);
  const exercises = await supabaseRequest(`workout_exercises?split_id=eq.${splitId}&select=*,exercises(name,category)&order=order_index`);

  if (!split || split.length === 0) return;

  const workout = split[0];
  document.getElementById('viewWorkoutTitle').textContent = workout.name;
  document.getElementById('viewWorkoutDescription').textContent = workout.description || 'No description';

  const exercisesList = document.getElementById('viewExercisesList');

  if (!exercises || exercises.length === 0) {
    exercisesList.innerHTML = '<p class="text-gray-500 text-center py-8">No exercises added yet</p>';
  } else {
    exercisesList.innerHTML = exercises.map((ex, idx) => `
      <div class="bg-gray-50 rounded-lg p-4">
        <div class="flex justify-between items-start">
          <div class="flex-1">
            <h4 class="font-semibold text-gray-900">${idx + 1}. ${ex.exercises.name}</h4>
            <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 mt-1">${ex.exercises.category}</span>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-4 mt-3 text-sm">
          <div>
            <span class="text-gray-600">Sets:</span>
            <span class="font-medium text-gray-900 ml-1">${ex.sets}</span>
          </div>
          <div>
            <span class="text-gray-600">Reps:</span>
            <span class="font-medium text-gray-900 ml-1">${ex.reps}</span>
          </div>
          <div>
            <span class="text-gray-600">Rest:</span>
            <span class="font-medium text-gray-900 ml-1">${ex.rest_seconds}s</span>
          </div>
        </div>
        ${ex.notes ? `<p class="text-sm text-gray-600 mt-2 italic">${ex.notes}</p>` : ''}
      </div>
    `).join('');
  }

  document.getElementById('viewWorkoutModal').classList.remove('hidden');
  document.getElementById('viewWorkoutModal').classList.add('flex');
}

async function deleteWorkout(splitId) {
  if (!confirm('Are you sure you want to delete this workout?')) return;

  await supabaseRequest(`workout_splits?id=eq.${splitId}`, 'DELETE');
  loadWorkouts();
}

async function loadExercises() {
  allExercises = await supabaseRequest('exercises?select=*&order=category,name');
}

function showExerciseSelection() {
  renderExerciseSelection();
  document.getElementById('exerciseModal').classList.remove('hidden');
  document.getElementById('exerciseModal').classList.add('flex');
}

function renderExerciseSelection(category = '') {
  const filtered = category
    ? allExercises.filter(ex => ex.category === category)
    : allExercises;

  const exerciseList = document.getElementById('exerciseSelectionList');
  exerciseList.innerHTML = filtered.map(ex => `
    <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-500 hover:bg-blue-50 cursor-pointer transition exercise-select-item" data-exercise-id="${ex.id}">
      <div class="flex justify-between items-center">
        <div>
          <h4 class="font-semibold text-gray-900">${ex.name}</h4>
          <span class="inline-block px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700 mt-1">${ex.category}</span>
        </div>
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
      </div>
      <p class="text-sm text-gray-600 mt-2">${ex.description}</p>
    </div>
  `).join('');

  document.querySelectorAll('.exercise-select-item').forEach(item => {
    item.addEventListener('click', () => {
      const exerciseId = item.dataset.exerciseId;
      const exercise = allExercises.find(ex => ex.id === exerciseId);
      addExerciseToWorkout(exercise);
      document.getElementById('exerciseModal').classList.add('hidden');
      document.getElementById('exerciseModal').classList.remove('flex');
    });
  });
}

function addExerciseToWorkout(exercise) {
  currentWorkoutExercises.push({
    exercise_id: exercise.id,
    exercise_name: exercise.name,
    exercise_category: exercise.category,
    sets: 3,
    reps: '8-12',
    rest_seconds: 60,
    notes: ''
  });

  renderWorkoutExercises();
}

function renderWorkoutExercises() {
  const exercisesList = document.getElementById('exercisesList');

  if (currentWorkoutExercises.length === 0) {
    exercisesList.innerHTML = '<p class="text-gray-500 text-center py-4">No exercises added yet. Click "Add Exercise" to get started.</p>';
    return;
  }

  exercisesList.innerHTML = currentWorkoutExercises.map((ex, idx) => `
    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
      <div class="flex justify-between items-start mb-3">
        <div>
          <h4 class="font-semibold text-gray-900">${ex.exercise_name}</h4>
          <span class="inline-block px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 mt-1">${ex.exercise_category}</span>
        </div>
        <button class="text-red-600 hover:text-red-700 font-bold text-xl remove-exercise" data-index="${idx}">&times;</button>
      </div>
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Sets</label>
          <input type="number" value="${ex.sets}" min="1" class="w-full px-3 py-2 border border-gray-300 rounded text-sm exercise-sets" data-index="${idx}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Reps</label>
          <input type="text" value="${ex.reps}" class="w-full px-3 py-2 border border-gray-300 rounded text-sm exercise-reps" data-index="${idx}">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-700 mb-1">Rest (sec)</label>
          <input type="number" value="${ex.rest_seconds}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded text-sm exercise-rest" data-index="${idx}">
        </div>
      </div>
      <div class="mt-3">
        <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
        <input type="text" value="${ex.notes}" placeholder="Optional notes" class="w-full px-3 py-2 border border-gray-300 rounded text-sm exercise-notes" data-index="${idx}">
      </div>
    </div>
  `).join('');

  document.querySelectorAll('.remove-exercise').forEach(btn => {
    btn.addEventListener('click', (e) => {
      const index = parseInt(e.target.dataset.index);
      currentWorkoutExercises.splice(index, 1);
      renderWorkoutExercises();
    });
  });

  document.querySelectorAll('.exercise-sets').forEach(input => {
    input.addEventListener('change', (e) => {
      const index = parseInt(e.target.dataset.index);
      currentWorkoutExercises[index].sets = parseInt(e.target.value);
    });
  });

  document.querySelectorAll('.exercise-reps').forEach(input => {
    input.addEventListener('change', (e) => {
      const index = parseInt(e.target.dataset.index);
      currentWorkoutExercises[index].reps = e.target.value;
    });
  });

  document.querySelectorAll('.exercise-rest').forEach(input => {
    input.addEventListener('change', (e) => {
      const index = parseInt(e.target.dataset.index);
      currentWorkoutExercises[index].rest_seconds = parseInt(e.target.value);
    });
  });

  document.querySelectorAll('.exercise-notes').forEach(input => {
    input.addEventListener('change', (e) => {
      const index = parseInt(e.target.dataset.index);
      currentWorkoutExercises[index].notes = e.target.value;
    });
  });
}

async function saveWorkout() {
  const name = document.getElementById('workoutName').value.trim();
  const description = document.getElementById('workoutDescription').value.trim();

  if (!name) {
    alert('Please enter a workout name');
    return;
  }

  const splitData = { name, description };
  const splitResult = await supabaseRequest('workout_splits', 'POST', splitData);

  if (!splitResult || splitResult.length === 0) {
    alert('Error creating workout');
    return;
  }

  const splitId = splitResult[0].id;

  for (let i = 0; i < currentWorkoutExercises.length; i++) {
    const ex = currentWorkoutExercises[i];
    await supabaseRequest('workout_exercises', 'POST', {
      split_id: splitId,
      exercise_id: ex.exercise_id,
      sets: ex.sets,
      reps: ex.reps,
      rest_seconds: ex.rest_seconds,
      notes: ex.notes,
      order_index: i
    });
  }

  closeWorkoutModal();
  loadWorkouts();
}

function openWorkoutModal() {
  currentWorkoutExercises = [];
  document.getElementById('workoutName').value = '';
  document.getElementById('workoutDescription').value = '';
  renderWorkoutExercises();
  document.getElementById('workoutModal').classList.remove('hidden');
  document.getElementById('workoutModal').classList.add('flex');
}

function closeWorkoutModal() {
  document.getElementById('workoutModal').classList.add('hidden');
  document.getElementById('workoutModal').classList.remove('flex');
}

document.getElementById('newWorkoutBtn').addEventListener('click', openWorkoutModal);
document.getElementById('closeModal').addEventListener('click', closeWorkoutModal);
document.getElementById('cancelBtn').addEventListener('click', closeWorkoutModal);
document.getElementById('saveWorkoutBtn').addEventListener('click', saveWorkout);
document.getElementById('addExerciseBtn').addEventListener('click', showExerciseSelection);

document.getElementById('closeExerciseModal').addEventListener('click', () => {
  document.getElementById('exerciseModal').classList.add('hidden');
  document.getElementById('exerciseModal').classList.remove('flex');
});

document.getElementById('closeViewModal').addEventListener('click', () => {
  document.getElementById('viewWorkoutModal').classList.add('hidden');
  document.getElementById('viewWorkoutModal').classList.remove('flex');
});

document.getElementById('categoryFilter').addEventListener('change', (e) => {
  renderExerciseSelection(e.target.value);
});

loadExercises();
loadWorkouts();

  </script>
</html>
