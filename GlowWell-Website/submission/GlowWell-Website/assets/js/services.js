document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("[data-service-form]");
    if (!form) {
        return;
    }

    form.addEventListener("submit", (event) => {
        const duration = Number(form.elements.duration.value);
        const price = Number(form.elements.price.value);
        if (!Number.isFinite(duration) || duration <= 0 || !Number.isFinite(price) || price <= 0) {
            event.preventDefault();
            window.alert("Enter a duration and price greater than zero.");
        }
    });
});
