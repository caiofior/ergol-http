# ergol-http

Ergol companion to serve #gemini capsules through http/https.
It is a http wrapper written in php working with ergol gemini server or in standalone.

## Copyright

author : [Adële](https://adele.work/)

ergol-http is under MIT/X Consortium License

ergol-http uses and provides a copy of Twitter Emoji aka twemoji packaged in a TrueType font format.
[twemoji](https://twemoji.twitter.com/) is also published under [MIT license](http://opensource.org/licenses/MIT).

Main repository on [Codeberg](https://codeberg.org/adele.work/ergol-http).


 * ## If launched with internal php web server
 * install ergol-http.php and style.css in an empty "http/" folder
 * under folder containing ergol.json.
 * Configuration in ergol.json file will be used (see ergol.gmi for details)
 * Launch with : php -S 0.0.0.0:8080 -t ./ ./ergol-http.php
 * 
 * ## If launched from a webserver (with same hostname of gemini server),
 * paste ergol-http.php into document root folder and rename it index.php.
 * Install style.css in the same folder.
 * Adapt ERGOL_JSON constant (see below)
 * Then create a rewrite rule 
 * Apache:
 *   RewriteEngine on
 *   RewriteRule   "^/(.*)"  "/?q=$1"  [R,L]
 *
 * lighttpd:
 *   server.modules += ("mod_rewrite")
 *   url.rewrite-once = ( "^/(.*)" => "/?q=/$1"  )
 *
 * gemini://adele.work/code/ergol/