// Scripts spécifiques à la page Home
document.addEventListener('DOMContentLoaded', function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('open_modal') === '1') {
        if (typeof openRepasModal === 'function') {
            openRepasModal();
            var url = new URL(window.location);
            url.searchParams.delete('open_modal');
            window.history.replaceState({}, '', url);
        }
    }
});
