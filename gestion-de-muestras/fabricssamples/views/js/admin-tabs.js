document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('form[id^="configuration_form"], form.defaultForm');
  if (!form) return;
  var markers = Array.prototype.slice.call(form.querySelectorAll('.fs-tab-marker'));
  if (!markers.length) return;
  var groups = markers.map(function (m) { return m.closest('.form-group') || m.parentNode; });
  var first = groups[0];
  var container = first.parentNode;
  var nav = document.createElement('ul'); nav.className = 'nav nav-tabs fs-admin-tabs';
  var content = document.createElement('div'); content.className = 'tab-content fs-admin-tab-content';
  var active = (window.location.hash || '#fs-tab-general').replace('#fs-tab-', '');
  markers.forEach(function (marker, idx) {
    var id = marker.getAttribute('data-tab');
    var title = marker.getAttribute('data-title') || id;
    var icon = marker.getAttribute('data-icon') || 'icon-cog';
    var li = document.createElement('li'); if (id === active || (!active && idx === 0)) li.className = 'active';
    li.innerHTML = '<a href="#fs-tab-' + id + '" data-toggle="tab"><i class="' + icon + '"></i> ' + title + '</a>'; nav.appendChild(li);
    var pane = document.createElement('div'); pane.className = 'tab-pane ' + ((id === active || (!active && idx === 0)) ? 'active' : ''); pane.id = 'fs-tab-' + id;
    var current = groups[idx].nextSibling;
    var stop = groups[idx + 1] || null;
    while (current && current !== stop) { var next = current.nextSibling; pane.appendChild(current); current = next; }
    content.appendChild(pane); groups[idx].remove();
  });
  container.insertBefore(nav, container.firstChild);
  container.insertBefore(content, nav.nextSibling);
  nav.addEventListener('click', function (e) {
    var a = e.target.closest('a'); if (!a) return;
    setTimeout(function(){ history.replaceState(null, '', a.getAttribute('href')); }, 0);
  });
});
