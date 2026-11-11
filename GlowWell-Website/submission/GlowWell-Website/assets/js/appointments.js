document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("[data-appointment-form]");
    if (!form) {
        return;
    }

    const staffField = form.querySelector("[name='staff_id']");
    const serviceField = form.querySelector("[name='service_id']");
    const dateField = form.querySelector("[name='appointment_date']");
    const timeField = form.querySelector("[name='appointment_time']");
    const alert = document.createElement("div");
    alert.className = "alert alert-warning d-none";
    alert.setAttribute("role", "alert");
    form.prepend(alert);

    const minuteOfDay = (value) => {
        const [hours, minutes] = value.split(":").map(Number);
        return hours * 60 + minutes;
    };

    const validateSchedule = () => {
        const staffId = staffField.value;
        const serviceId = serviceField.value;
        const appointmentDate = dateField.value;
        const appointmentTime = timeField.value;
        if (!staffId || !serviceId || !appointmentDate || !appointmentTime) {
            alert.classList.add("d-none");
            timeField.setCustomValidity("");
            return true;
        }

        const service = window.GlowWellServices.find((entry) => String(entry.service_id) === serviceId);
        const localDate = new Date(`${appointmentDate}T12:00:00`);
        const weekday = localDate.getDay() || 7;
        const schedule = window.GlowWellSchedules.find(
            (entry) => String(entry.staff_id) === staffId && Number(entry.weekday) === weekday
        );
        const start = minuteOfDay(appointmentTime);
        const shiftStart = schedule ? minuteOfDay(schedule.start_time) : 0;
        const shiftEnd = schedule ? minuteOfDay(schedule.end_time) : 0;

        if (!schedule || !service || start < shiftStart || start + Number(service.duration) > shiftEnd) {
            alert.textContent = "This team member is not available for the full treatment at that time. Check their weekly schedule or choose another time.";
            alert.classList.remove("d-none");
            timeField.setCustomValidity(alert.textContent);
            return false;
        }

        alert.classList.add("d-none");
        timeField.setCustomValidity("");
        return true;
    };

    [staffField, serviceField, dateField, timeField].forEach((field) => {
        field.addEventListener("change", validateSchedule);
    });
    form.addEventListener("submit", (event) => {
        if (!validateSchedule()) {
            event.preventDefault();
            timeField.reportValidity();
        }
    });
});
