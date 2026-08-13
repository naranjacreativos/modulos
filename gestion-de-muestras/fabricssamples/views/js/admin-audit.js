document.addEventListener('DOMContentLoaded', function () {
  var checkAll = document.querySelector('.js-fs-audit-check-all');
  if (!checkAll || !checkAll.form) {
    return;
  }

  var checkboxes = Array.prototype.slice.call(
    checkAll.form.querySelectorAll('.js-fs-audit-checkbox')
  );

  var synchronizeCheckAll = function () {
    var checked = checkboxes.filter(function (checkbox) {
      return checkbox.checked;
    }).length;

    checkAll.checked = checkboxes.length > 0 && checked === checkboxes.length;
    checkAll.indeterminate = checked > 0 && checked < checkboxes.length;
  };

  checkAll.addEventListener('change', function () {
    checkboxes.forEach(function (checkbox) {
      checkbox.checked = checkAll.checked;
    });
    checkAll.indeterminate = false;
  });

  checkboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', synchronizeCheckAll);
  });

  synchronizeCheckAll();
});
