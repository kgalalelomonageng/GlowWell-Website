document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".schedule-toggle").forEach((toggle) => {
        const row = toggle.closest(".row");
        const timeFields = row.querySelectorAll(".schedule-time");
        const updateAvailability = () => {
            timeFields.forEach((field) => {
                field.disabled = !toggle.checked;
                field.required = toggle.checked;
            });
        };

        toggle.addEventListener("change", updateAvailability);
        updateAvailability();
    });
});
