export const tags = (taglist, assignedTags = []) => {
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.querySelector('#thread-tags-container');
        const tagMap = new Map();
        taglist.forEach(tag => tagMap.set(tag.name, tag.tagid));
        container.innerHTML = `<d2l-labs-attribute-picker aria-label="attributes"
            attribute-list='${JSON.stringify(assignedTags)}'
            assignable-attributes='${JSON.stringify(taglist.map(x => x.name))}'></d2l-labs-attribute-picker>`;

        const setTagIds = (e) => {
            document.querySelector('[name="thread-tag-ids"]')
                .setAttribute('value',
                    e.detail.attributeList.map(x => tagMap.get(x)).join(',')
                );
        };
        container.addEventListener('d2l-attributes-changed', setTagIds);
    });
};