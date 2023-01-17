$(document).ready(function() {
    if(localStorage.version !== '1') {
        localStorage.clear();
        localStorage.version = '1';
        console.log('Storage Set: ' + '1');
    }
});
