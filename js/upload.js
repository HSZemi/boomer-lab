const GAMES = [];
let NUMBER_OF_GAMES = 0;

document.getElementById('check-code-btn').onclick = () => {
    fetch('api.php?check=' + document.getElementById('input-match-code').value)
        .then((response) => {
            return response.json();
        })
        .then((data) => {
            if (!data.exists) {
                alert('Unknown code. Maybe a typo?');
            } else {
                console.log(data);
                NUMBER_OF_GAMES = data.match.number_of_games;
                document.getElementById('input-match-code').disabled = true;
                document.getElementById('code-check').classList.add('hidden');
                document.getElementById('player-name').innerText = data.match.player;
                document.getElementById('round-name').innerText = data.match.round;
                document.getElementById('opponent-name').innerText = data.match.opponent;
                document.getElementById('upload-form').classList.remove('hidden');
            }
        });
};

document.getElementById('files-input').onchange = (event) => {
    const files = event.target.files;
    GAMES.length = 0;
    let index = 0;
    for (let file of files) {
        if (index < NUMBER_OF_GAMES) {
            GAMES.push([file.name]);
        } else {
            GAMES[GAMES.length - 1].push(file.name);
        }
        index++;
    }
    updateGameSorter();
};

function updateGameSorter() {
    const gameSorter = document.getElementById('game-sorter');
    gameSorter.innerHTML = '';
    for (let i = 0; i < GAMES.length; i++) {
        gameSorter.innerHTML += `<li>Game ${i + 1}</li>`;
        let gameHtml = '<ul>';
        for (let j = 0; j < GAMES[i].length; j++) {
            gameHtml += `<li>
                            <span class="badge badge-secondary">${GAMES[i][j]}</span>
                            <button class="btn btn-outline-secondary btn-sm btn-up">↑</button>
                            <button class="btn btn-outline-secondary btn-sm btn-down">↓</button>
                         </li>`;
        }
        gameHtml += '</ul>';
        gameSorter.innerHTML += gameHtml;
    }
    for (let btn of document.querySelectorAll('.btn-up')) {
        btn.onclick = (event) => {
            const filename = event.target.parentElement.firstElementChild.textContent;
            moveUp(filename);
            updateGameSorter();
        }
    }
    for (let btn of document.querySelectorAll('.btn-down')) {
        btn.onclick = (event) => {
            const filename = event.target.parentElement.firstElementChild.textContent;
            moveDown(filename);
            updateGameSorter();
        }
    }
}

function removeEmptyGames() {
    for (let i = GAMES.length - 1; i >= 0; i--) {
        if (GAMES[i].length === 0) {
            GAMES.splice(i, 1);
        }
    }

}

function moveUp(filename) {
    for (let gameIndex = 0; gameIndex < GAMES.length; gameIndex++) {
        for (let subGameIndex = 0; subGameIndex < GAMES[gameIndex].length; subGameIndex++) {
            if (GAMES[gameIndex][subGameIndex] === filename) {
                if (subGameIndex > 0) {
                    GAMES[gameIndex][subGameIndex] = GAMES[gameIndex][subGameIndex - 1];
                    GAMES[gameIndex][subGameIndex - 1] = filename;
                    return;
                } else if (gameIndex > 0) {
                    GAMES[gameIndex - 1].push(filename);
                    GAMES[gameIndex].splice(subGameIndex, 1);
                    return;
                }
            }
        }
    }
    removeEmptyGames();
}

function moveDown(filename) {
    for (let gameIndex = 0; gameIndex < GAMES.length; gameIndex++) {
        for (let subGameIndex = 0; subGameIndex < GAMES[gameIndex].length; subGameIndex++) {
            if (GAMES[gameIndex][subGameIndex] === filename) {
                if (subGameIndex < GAMES[gameIndex].length - 1) {
                    GAMES[gameIndex][subGameIndex] = GAMES[gameIndex][subGameIndex + 1];
                    GAMES[gameIndex][subGameIndex + 1] = filename;
                    return;
                } else if (gameIndex < GAMES.length - 1) {
                    GAMES[gameIndex + 1].unshift(filename);
                    GAMES[gameIndex].splice(subGameIndex, 1);
                    return;
                }
            }
        }
    }
    removeEmptyGames();
}

function performUpload() {
    const formData = new FormData();
    const filesInput = document.getElementById('files-input');
    removeEmptyGames();

    let numberOfFiles = 0;
    for (let game of GAMES) {
        for (let file of game) {
            numberOfFiles++;
        }
    }
    if (numberOfFiles === 0) {
        alert('Please add recorded game files first');
        return;
    }

    formData.append('code', document.getElementById('input-match-code').value);
    formData.append('config', JSON.stringify(GAMES));
    for (let i = 0; i < filesInput.files.length; i++) {
        formData.append('recs[]', filesInput.files[i]);
    }

    fetch('api.php', {
        method: 'POST',
        body: formData,
    })
        .then((response) => response.json())
        .then((result) => {
            console.log('Success:', result);
            if (!result.success) {
                alert(result.message);
            } else {
                document.getElementById('upload-form').classList.add('hidden');
                document.getElementById('upload-success' +
                    '').classList.remove('hidden');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
}
