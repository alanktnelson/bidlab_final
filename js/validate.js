// Friendly checks before a form is sent. The server always checks everything again.

const authForm = document.querySelector('#auth-form');
const confirmInput = authForm?.querySelector('[name="confirm_password"]');

if (confirmInput) {
    const passwordInput = authForm.querySelector('[name="password"]');
    const compare = () => {
        confirmInput.setCustomValidity(confirmInput.value === passwordInput.value ? '' : 'Passwords do not match.');
    };
    confirmInput.addEventListener('input', compare);
    passwordInput.addEventListener('input', compare);
}

const listingForm = document.querySelector('#listing-form');

if (listingForm) {
    const { maxPhotos, maxBytes, minMinutes, maxDays } = listingForm.dataset;
    const photos = listingForm.querySelector('#photos');
    const previews = listingForm.querySelector('#photo-previews');
    const photosError = listingForm.querySelector('#photos-error');
    const closesInput = listingForm.querySelector('#closes-at');
    const closesUtc = listingForm.querySelector('#closes-at-utc');

    const toLocalInput = (date) => new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const now = Date.now();
    closesInput.min = toLocalInput(new Date(now + Number(minMinutes) * 60000));
    closesInput.max = toLocalInput(new Date(now + Number(maxDays) * 86400000));
    if (!closesInput.value) closesInput.value = toLocalInput(new Date(now + 3 * 86400000));

    const photoProblem = () => {
        const files = [...photos.files];
        if (files.length > Number(maxPhotos)) return `Add no more than ${maxPhotos} photos.`;
        if (files.some((file) => file.size > Number(maxBytes))) return 'Each photo must be 2 MB or smaller.';
        return '';
    };

    const showPhotoProblem = () => {
        const problem = photoProblem();
        photosError.textContent = problem;
        photosError.hidden = problem === '';
        return problem;
    };

    photos.addEventListener('change', () => {
        previews.replaceChildren(...[...photos.files].map((file) => {
            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = '';
            return image;
        }));
        showPhotoProblem();
    });

    listingForm.addEventListener('submit', (event) => {
        if (showPhotoProblem()) {
            event.preventDefault();
            return;
        }
        // The server stores UTC, so send the local time the seller picked in UTC.
        closesUtc.value = new Date(closesInput.value).toISOString();
    });
}
