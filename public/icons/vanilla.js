// Food Icons — async sprite loader for vanilla JS
// Usage:
//   <script src="food-icons/vanilla.js"></script>
//   <svg class="food-icon" width="24" height="24"><use href="#steak"/></svg>
//
// Or programmatically:
//   FoodIcons.load('/path/to/food-icons.svg')
//   FoodIcons.render('steak', 32, 'my-class') → SVG string

(function() {
  'use strict';

  var loaded = false;

  var FoodIcons = {
    load: function(spritePath) {
      if (loaded) return Promise.resolve();
      loaded = true;

      return fetch(spritePath)
        .then(function(r) { return r.text(); })
        .then(function(svg) {
          var div = document.createElement('div');
          div.style.display = 'none';
          div.setAttribute('aria-hidden', 'true');
          div.innerHTML = svg;
          document.body.insertBefore(div, document.body.firstChild);
        });
    },

    render: function(name, size, className) {
      size = size || 24;
      var cls = className ? ' class="' + className + '"' : '';
      return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" stroke="currentColor"' + cls + '>' +
        '<use href="#' + name + '"/>' +
        '</svg>';
    },

    el: function(name, size, className) {
      var tpl = document.createElement('template');
      tpl.innerHTML = FoodIcons.render(name, size, className);
      return tpl.content.firstElementChild;
    }
  };

  // Auto-load: if script has data-src, load sprite automatically
  var script = document.currentScript;
  if (script && script.dataset.src) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function() {
        FoodIcons.load(script.dataset.src);
      });
    } else {
      FoodIcons.load(script.dataset.src);
    }
  }

  window.FoodIcons = FoodIcons;
})();
