document.getElementById('check-code-btn').onclick = () => {
    fetch('api.php?check=' + document.getElementById('input-match-code').value)
        .then((response) => {
            return response.json();
        })
        .then((myJson) => {
            console.log(myJson);
        });
};