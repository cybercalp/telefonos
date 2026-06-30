document.addEventListener('DOMContentLoaded', () => {
   const elementModal = document.getElementById('staticBackdrop');
   if (elementModal !== null) {
      const myModal = new bootstrap.Modal(elementModal, {});
      document.onreadystatechange = function () {
         myModal.show();
      };
   }
});

window.addEventListener('hide.bs.modal', () => {
    if (document.activeElement instanceof HTMLElement) {
        document.activeElement.blur();
    }
});
