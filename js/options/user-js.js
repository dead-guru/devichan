/*
 * options/user-js.js - allow user enter custom javascripts
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/options.js';
 *   $config['additional_javascript'][] = 'js/options/user-js.js';
 */

+function(){

if (!window.Options) return;

var tab = Options.get_tab('user-code') || Options.add_tab('user-code', 'fa fa-code', _('Custom code'));
var section = $('<fieldset></fieldset>').append($('<legend></legend>').text(_('User JS'))).appendTo(tab.content);

var textarea = $("<textarea spellcheck='false'></textarea>").attr('aria-label', _('User JS')).css({
  "font-size": 13,
  "font-family": "monospace",
  display: "block",
  width: "100%",
  height: 220,
  "min-height": 180,
  margin: "0 0 12px",
  padding: 8,
  resize: "vertical"
}).appendTo(section);
var submit = $("<input type='button' value='"+_("Update custom Javascript")+"'>").click(function() {
  localStorage.user_js = textarea.val();
  document.location.reload();
}).appendTo(section);

var apply_js = function() {
  var proc = function() {
    $('.user-js').remove();
    $('script')
      .last()
      .after($("<script></script>")
        .addClass("user-js")
        .text(localStorage.user_js)
      );
  }

  if (/immediate()/.test(localStorage.user_js)) {
    proc(); // Apply the script immediately
  }
  else {
    $(proc); // Apply the script when the page fully loads
  }
};

var update_textarea = function() {
  if (!localStorage.user_js) {
    textarea.text("/* "+_("Enter here your own Javascript code...")+" */\n" +
                  "/* "+_("Have a backup of your storage somewhere, as messing here may render you this website unusable.")+" */\n" +
                  "/* "+_("You can include JS files from remote servers, for example:")+" */\n" +
                  'load_js("http://example.com/script.js");');
  }
  else {
    textarea.text(localStorage.user_js);
    apply_js();
  }
};

update_textarea();


// User utility functions
window.load_js = function(url) {
  $('script')
    .last()
    .after($("<script></script>")
      .prop("type", "text/javascript")
      .prop("src", url)
    );
};
window.immediate = function() { // A dummy function.
}

}();
