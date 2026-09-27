/*
 * options/user-css.js - allow user enter custom css entries
 *
 * Copyright (c) 2014 Marcin Łabanowski <marcin@6irc.net>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/options.js';
 *   $config['additional_javascript'][] = 'js/options/user-css.js';
 */

+function(){

if (!window.Options) return;

var tab = Options.get_tab('user-code') || Options.add_tab('user-code', 'fa fa-code', _('Custom code'));
var section = $('<fieldset></fieldset>').append($('<legend></legend>').text(_('User CSS'))).appendTo(tab.content);

var textarea = $("<textarea spellcheck='false'></textarea>").attr('aria-label', _('User CSS')).css({
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
var submit = $("<input type='button' value='"+_("Update custom CSS")+"'>").click(function() {
  localStorage.user_css = textarea.val();
  apply_css();
}).appendTo(section);

var apply_css = function() {
  $('.user-css').remove();
  $('link[rel="stylesheet"]')
    .last()
    .after($("<style></style>")
      .addClass("user-css")
      .text(localStorage.user_css)
    );
};

var update_textarea = function() {
  if (!localStorage.user_css) {
    textarea.text("/* "+_("Enter here your own CSS rules...")+" */\n" +
                  "/* "+_("If you want to make a redistributable style, be sure to have a Yotsuba B theme selected.")+" */\n" +
                  "/* "+_("You can include CSS files from remote servers, for example:")+" */\n" +
                  '@import "http://example.com/style.css";');
  }
  else {
    textarea.text(localStorage.user_css);
    apply_css();
  }
};

update_textarea();


}();
