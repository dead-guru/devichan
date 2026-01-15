$(document).ready(function() {
    if(localStorage.version !== '3') {
        console.log('Clearing localStorage');
        localStorage.clear();
        localStorage.version = '3';
    }
});
