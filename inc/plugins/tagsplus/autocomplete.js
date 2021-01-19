const normalizeString = (str) => {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim().toLowerCase();
};

const KEYCODES = {
    down: 40,
    up: 38,
    enter: 13,
    tab: 9
};

let autocompleteFocus;

// remove all autocomplete matches except this target
const destroyAutoMatches = (excludeElem) => {
    const inputs = Array.from(document.querySelectorAll('.autocomplete-input'));
    const matchElems = document.querySelectorAll('.autocomplete-item');

    for (let matchElem of matchElems) {
        if (excludeElem !== matchElem && !inputs.find(x => x === excludeElem)) {
            matchElem.remove();
        }
    }
    document.querySelector('.autocomplete-list').style.display = 'none';
}

/*execute a function when someone clicks in the document:*/
document.addEventListener("click", (e) => {
    autocompleteFocus = -1;
    destroyAutoMatches(e.target);
});

/**
 * Add autocompletion to a text input
 * @param {HTMLElement} input  - The input[type=text] element
 * @param {Array} array - The array of strings that can be autocompleted
 * @param {function} callback - Callback function to be called on autocomplete
 */
export const autocomplete = (input, array, callback = () => {}) => {
    input.classList.add('autocomplete-input');

    const listElem = document.createElement('div');
    listElem.setAttribute('role', 'listbox');
    listElem.classList.add('autocomplete-list');
    input.parentNode.appendChild(listElem);
    destroyAutoMatches();

    const onInputEnter = (e) => {
        const value = e.target.value;
        /*close any already open lists of autocompleted values*/
        destroyAutoMatches();
        if (!value) return;
        autocompleteFocus = -1;

        for (let item of array) {
            /*check if the item starts with the same letters as the text field value:*/
            if (normalizeString(item.substr(0, value.length)) === normalizeString(value)) {
                const matchElem = document.createElement('div');
                matchElem.setAttribute('role', 'option');
                matchElem.setAttribute('tabindex', '0');
                matchElem.classList.add('autocomplete-item');
                matchElem.innerHTML = `<strong>${item.substr(0, value.length)}</strong>${item.substr(value.length)}`;

                matchElem.addEventListener("click", (e) => {
                    /*insert the value for the autocomplete text field:*/
                    input.value = e.target.innerText;
                    input.setAttribute('value', e.target.innerText);
                    destroyAutoMatches();
                    callback(e.target.innerText);
                });
                listElem.appendChild(matchElem);
            }
        }
        const matchElems = listElem.querySelectorAll('.autocomplete-item');
        if (matchElems.length) listElem.style.display = 'block';
    };
    input.addEventListener("input", onInputEnter);
    //input.addEventListener("focus", onInputEnter);

    const onKeyDown = (e) => {
        const listElem = input.parentNode.querySelector(`.autocomplete-list`);
        const matchElems = listElem.querySelectorAll('.autocomplete-item');
        if (!matchElems.length) return;
        switch (e.keyCode) {
            case KEYCODES.down:
                autocompleteFocus++;
                matchElems?.item(autocompleteFocus)?.focus();
                break;
            case KEYCODES.up:
                autocompleteFocus--;
                matchElems?.item(autocompleteFocus)?.focus();
                break;
            case KEYCODES.enter:
            case KEYCODES.tab:
                e.stopPropagation();
                e.preventDefault();
                matchElems.item(0).click();
                break;
        }
    };
    input.parentNode.addEventListener("keydown", onKeyDown);
};
