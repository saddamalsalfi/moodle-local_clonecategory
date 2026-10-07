// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Collapsible, searchable category selectors.
 * @module local_clonecategory/category_tree
 * @copyright 2026 Saddam Al-Slfi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Create a node using plain text.
 * @param {string} tag Element tag
 * @param {string} className CSS classes
 * @param {string} [text] Visible text
 * @returns {HTMLElement} Node
 */
const element = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    if (text !== undefined) {
        node.textContent = text;
    }
    return node;
};

const build = (field, nodes, labels, source) => {
    const select = document.getElementById('id_' + field);
    if (!select || select.disabled || select.dataset.treeReady) {
        return;
    }
    const permitted = new Set(Array.from(select.options).map(option => option.value));
    nodes = nodes.filter(node => permitted.has(String(node.id)));
    const panel = element('div', 'clone-tree-panel');
    const hint = element('p', 'clone-tree-hint', source ? labels.singlesourcehint : labels.targetcategory);
    const search = element('input', 'form-control clone-tree-search');
    search.type = 'search';
    search.placeholder = labels.searchcategories;
    search.setAttribute('aria-label', labels.searchcategories + ' — ' + (source ? labels.sourcecategory : labels.targetcategory));
    const toolbar = element('div', 'clone-tree-toolbar');
    const expand = element('button', 'btn btn-sm btn-outline-secondary', labels.expandall);
    const collapse = element('button', 'btn btn-sm btn-outline-secondary', labels.collapseall);
    expand.type = collapse.type = 'button';
    toolbar.append(expand, collapse);
    const list = element('ul', 'clone-tree-list');
    const selected = element('p', 'clone-tree-selected');
    selected.setAttribute('aria-live', 'polite');
    const empty = element('p', 'clone-tree-empty', labels.nosearchresults);
    empty.hidden = true;
    panel.append(hint, search, toolbar, list, empty, selected);
    const entries = new Map();
    const ids = new Set(nodes.map(node => String(node.id)));
    const children = new Map();
    nodes.forEach(node => {
        const parent = ids.has(String(node.parent)) ? String(node.parent) : 'root';
        if (!children.has(parent)) {
            children.set(parent, []);
        }
        children.get(parent).push(node);
    });
    const selectNode = id => {
        select.value = id;
        select.dispatchEvent(new Event('change', {bubbles: true}));
        entries.forEach((entry, key) => {
            entry.input.checked = key === id;
            entry.row.classList.toggle('is-selected', key === id);
        });
        const chosen = entries.get(id);
        selected.textContent = chosen ? labels.selectedcategory + ': ' + chosen.name : '';
    };
    const appendNode = (node, parentList) => {
        const id = String(node.id);
        const item = element('li', 'clone-tree-node');
        const row = element('div', 'clone-tree-row');
        const input = element('input', 'clone-tree-choice');
        input.type = source ? 'checkbox' : 'radio';
        input.name = source ? 'clone_source_choice' : 'clone_target_choice';
        input.value = id;
        input.id = 'clone-tree-' + field + '-' + id;
        input.setAttribute('aria-label', (source ? labels.sourcecategory : labels.targetcategory) + ': ' + node.name);
        const sub = element('ul', 'clone-tree-children');
        sub.id = input.id + '-children';
        const descendants = children.get(id) || [];
        const title = element(descendants.length ? 'button' : 'label', 'clone-tree-title', node.name);
        if (descendants.length) {
            title.type = 'button';
            title.setAttribute('aria-expanded', 'false');
            title.setAttribute('aria-controls', sub.id);
            sub.hidden = true;
            title.addEventListener('click', () => {
                sub.hidden = !sub.hidden;
                title.setAttribute('aria-expanded', String(!sub.hidden));
            });
        } else {
            title.htmlFor = input.id;
        }
        input.addEventListener('change', () => selectNode(input.checked ? id : ''));
        row.append(input, title);
        item.append(row);
        if (descendants.length) {
            item.append(sub);
        }
        parentList.append(item);
        entries.set(id, {
            item, row, input, title, sub, name: node.name, parent: String(node.parent), expandable: descendants.length > 0,
        });
        descendants.forEach(child => appendNode(child, sub));
    };
    if (!source && permitted.has('0')) {
        appendNode({id: 0, parent: -1, name: labels.top}, list);
    }
    (children.get('root') || []).forEach(node => appendNode(node, list));
    const revealSelected = () => {
        let entry = entries.get(select.value);
        const visited = new Set();
        while (entry && !visited.has(entry.parent)) {
            visited.add(entry.parent);
            entry = entries.get(entry.parent);
            if (entry && entry.expandable) {
                entry.sub.hidden = false;
                entry.title.setAttribute('aria-expanded', 'true');
            }
        }
    };
    const filter = () => {
        const term = search.value.trim().toLocaleLowerCase();
        const visible = new Set();
        entries.forEach((entry, id) => {
            if (!term || entry.name.toLocaleLowerCase().includes(term)) {
                visible.add(id);
                let parent = entry.parent;
                while (entries.has(parent) && !visible.has(parent)) {
                    visible.add(parent);
                    parent = entries.get(parent).parent;
                }
            }
        });
        entries.forEach((entry, id) => {
            entry.item.hidden = !visible.has(id);
            if (entry.expandable) {
                entry.sub.hidden = term ? false : !entry.open;
                entry.title.setAttribute('aria-expanded', String(!entry.sub.hidden));
            }
        });
        empty.hidden = visible.size > 0;
        if (!term) {
            revealSelected();
        }
    };
    entries.forEach(entry => {
        if (entry.expandable) {
            entry.title.addEventListener('click', () => {
                entry.open = !entry.sub.hidden;
            });
        }
    });
    const toggleAll = open => {
        search.value = '';
        entries.forEach(entry => {
            entry.open = open;
            entry.item.hidden = false;
            if (entry.expandable) {
                entry.sub.hidden = !open;
                entry.title.setAttribute('aria-expanded', String(open));
            }
        });
        empty.hidden = entries.size > 0;
    };
    expand.addEventListener('click', () => toggleAll(true));
    collapse.addEventListener('click', () => toggleAll(false));
    search.addEventListener('input', filter);
    select.insertAdjacentElement('afterend', panel);
    select.hidden = true;
    select.dataset.treeReady = '1';
    // Keep the original select in the form for Moodle's server-side validation and submission.
    selectNode(select.value);
    revealSelected();
};

/**
 * Enhance the native fields with category data held in a JSON element.
 * @param {string} dataid Configuration element ID
 */
export const init = dataid => {
    const config = document.getElementById(dataid);
    if (!config) {
        return;
    }
    let data;
    try {
        data = JSON.parse(config.textContent);
    } catch (error) {
        return;
    }
    if (!Array.isArray(data.nodes) || !data.labels) {
        return;
    }
    build('sourcecategory', data.nodes, data.labels, true);
    build('targetcategory', data.nodes, data.labels, false);
};
