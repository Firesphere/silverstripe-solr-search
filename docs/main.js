var offset = document.getElementsByClassName('active')[0].offsetTop;
document.getElementById('menu').scrollTop = offset - 10;
var _paq = window._paq || [];
/* tracker methods like "setCustomDimension" should be called before "trackPageView" */
_paq.push(['trackPageView']);
_paq.push(['enableLinkTracking']);
(function () {
  var u = "https://piwik.casa-laguna.net/";
  _paq.push(['setTrackerUrl', u + 'matomo.php']);
  _paq.push(['setSiteId', '13']);
  var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
  g.type = 'text/javascript';
  g.async = true;
  g.defer = true;
  g.src = u + 'matomo.js';
  s.parentNode.insertBefore(g, s);
})();
// FROM http://purecss.io/js/ui.js
(function (window, document) {
  var layout = document.getElementById("layout"),
    menu = document.getElementById("menu"),
    menuLink = document.getElementById("menuLink");

  function toggleClass(element, className) {
    var classes = element.className.split(/\s+/),
      length = classes.length,
      i = 0;

    for (; i < length; i++) {
      if (classes[i] === className) {
        classes.splice(i, 1);
        break;
      }
    }
    // The className is not found
    if (length === classes.length) {
      classes.push(className);
    }

    element.className = classes.join(" ");
  }

  menuLink.onclick = function (e) {
    var active = "active";

    e.preventDefault();
    toggleClass(layout, active);
    toggleClass(menu, active);
    toggleClass(menuLink, active);
  };
})(this, this.document);
(function () {
  var codeTags = document.querySelectorAll('code[class^="language-"]');
  for (var tag of codeTags) {
    tag.className = tag.className.replace("language-", "prettyprint lang-");
  }
})();
