  (function () {
    const purchaseDateInput = document.getElementById('DateBuy');
    const resourceDaysInput = document.getElementById('ResourceDays');
    const resourceEndInput = document.getElementById('ResourceEndDate');

    if (!purchaseDateInput || !resourceDaysInput || !resourceEndInput) {
      return;
    }

    const originalEndDate = resourceEndInput.value;

    const formatDate = (dateObj) => {
      const pad = (num) => String(num).padStart(2, '0');
      return `${dateObj.getFullYear()}-${pad(dateObj.getMonth() + 1)}-${pad(dateObj.getDate())}`;
    };

    const updateResourceEnd = () => {
      const purchaseVal = purchaseDateInput.value;
      const days = parseInt(resourceDaysInput.value, 10);

      if (!purchaseVal || Number.isNaN(days) || days <= 0) {
        resourceEndInput.value = originalEndDate;
        return;
      }

      const purchaseDate = new Date(purchaseVal);
      if (Number.isNaN(purchaseDate.getTime())) {
        resourceEndInput.value = originalEndDate;
        return;
      }

      purchaseDate.setDate(purchaseDate.getDate() + days);
      resourceEndInput.value = formatDate(purchaseDate);
    };

    purchaseDateInput.addEventListener('change', updateResourceEnd);
    resourceDaysInput.addEventListener('input', updateResourceEnd);

    updateResourceEnd();
  })();
