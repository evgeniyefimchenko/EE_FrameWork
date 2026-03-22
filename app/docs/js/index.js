document.addEventListener('DOMContentLoaded', function () {
    var filterInput = document.getElementById('docs-nav-filter');
    if (filterInput) {
        filterInput.addEventListener('input', function () {
            var needle = String(filterInput.value || '').toLowerCase().trim();
            var groups = document.querySelectorAll('.docs-nav-group');

            groups.forEach(function (group) {
                var anyVisible = false;
                var items = group.querySelectorAll('.docs-nav-item');

                items.forEach(function (item) {
                    var haystack = String(item.getAttribute('data-doc-search') || '').toLowerCase();
                    var visible = needle === '' || haystack.indexOf(needle) !== -1;
                    item.style.display = visible ? '' : 'none';
                    if (visible) {
                        anyVisible = true;
                    }
                });

                group.style.display = anyVisible ? '' : 'none';
            });
        });
    }

    var articleRoot = document.getElementById('docs-article-content');
    if (!articleRoot) {
        return;
    }

    articleRoot.querySelectorAll('h2[id], h3[id], h4[id]').forEach(function (heading) {
        if (heading.querySelector('.docs-anchor-link')) {
            return;
        }
        var anchor = document.createElement('a');
        anchor.className = 'docs-anchor-link';
        anchor.href = '#' + heading.id;
        anchor.setAttribute('aria-label', 'Ссылка на раздел');
        anchor.innerHTML = '<i class="fa-solid fa-link"></i>';
        heading.appendChild(anchor);
    });
});
