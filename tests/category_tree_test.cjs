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
 * Category selector DOM behaviour regression tests.
 * @copyright 2026 Saddam Al-Slfi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Copyright 2026 Saddam Al-Slfi. GNU GPL v3 or later.
// Isolated DOM-behaviour tests; layout still requires a Moodle browser test.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
class Element {
    constructor(tag) {
        this.tagName = tag;
        this.children = [];
        this.dataset = {};
        this.attributes = {};
        this.handlers = {};
        this.hidden = false;
        this.value = '';
        this.classList = {toggle: () => {}};
    }
    append(...nodes) { this.children.push(...nodes); }
    setAttribute(key, value) { this.attributes[key] = value; }
    addEventListener(type, handler) { (this.handlers[type] ||= []).push(handler); }
    dispatchEvent(event) { (this.handlers[event.type] || []).forEach(fn => fn(event)); }
    insertAdjacentElement(position, node) { this.panel = node; }
}
const source = new Element('select');
source.options = [{value: ''}, {value: '1'}, {value: '2'}, {value: '3'}];
const target = new Element('select');
target.options = [{value: '0'}, {value: '1'}, {value: '2'}, {value: '3'}];
target.value = '0';
const document = {
    createElement: tag => new Element(tag),
    getElementById: id => id === 'clonecategory-tree-data' ? config : id === 'id_sourcecategory' ? source : target,
};
const config = {textContent: ""};
let moduleExports;
vm.runInNewContext(fs.readFileSync(__dirname + '/../amd/build/category_tree.min.js', 'utf8'), {
    document, Event: class { constructor(type) { this.type = type; } },
    define: (name, deps, factory) => { const exports = {}; moduleExports = factory(exports) || exports; },
});
const labels = Object.fromEntries(['singlesourcehint', 'targetcategory', 'sourcecategory', 'searchcategories',
    'expandall', 'collapseall', 'selectedcategory', 'nosearchresults', 'top'].map(k => [k, k]));
config.textContent = JSON.stringify({nodes: [{id: 1, parent: 0, name: 'Root'}, {id: 2, parent: 1, name: 'Child'},
    {id: 3, parent: 0, name: 'Other'}], labels});
moduleExports.init('clonecategory-tree-data');
const all = node => [node, ...node.children.flatMap(all)];
const nodes = all(source.panel);
const choices = nodes.filter(node => node.className === 'clone-tree-choice');
assert.equal(choices.length, 3);
const choose = id => {
    const choice = choices.find(node => node.value === String(id));
    choice.checked = true;
    choice.dispatchEvent({type: 'change'});
};
choose(2);
choose(3);
assert.equal(source.value, '3');
assert.equal(choices.filter(node => node.checked).length, 1);
const parent = nodes.find(node => node.className === 'clone-tree-title' && node.textContent === 'Root');
assert.equal(parent.attributes['aria-expanded'], 'false');
parent.dispatchEvent({type: 'click'});
assert.equal(parent.attributes['aria-expanded'], 'true');
parent.dispatchEvent({type: 'click'});
assert.equal(parent.attributes['aria-expanded'], 'false');
const search = nodes.find(node => node.type === 'search');
search.value = 'Child';
search.dispatchEvent({type: 'input'});
assert.equal(parent.attributes['aria-expanded'], 'true');
const child = choices.find(node => node.value === '2');
assert.ok(child);
const empty = nodes.find(node => node.className === 'clone-tree-empty');
search.value = 'Not found';
search.dispatchEvent({type: 'input'});
assert.equal(empty.hidden, false);
assert.equal(target.value, '0');
assert.equal(all(target.panel).filter(node => node.type === 'radio').length, 4);
console.log('PASS: single source, expand/collapse, search, empty state, root destination.');
