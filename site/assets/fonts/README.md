# Fonts, checked in

These are the exact `latin` woff2 files Google Fonts serves for Inter,
IBM Plex Mono and Instrument Serif, downloaded once and committed.

They are here because `next/font/google` fetches them from
fonts.gstatic.com **at build time**, and the deploy failed twice in a row
when that fetch came back half-done — Turbopack had the CSS but could not
resolve the font files, so a content change that had nothing to do with
typography could not ship. A build that reaches the network is a build
that can fail for reasons no commit explains.

All three are SIL Open Font License 1.1, which permits redistribution with
the licence included; the three `OFL-*.txt` files beside them are it.

To refresh one, request the CSS with a browser User-Agent, take the URL in
the `/* latin */` block, and overwrite the file:

    curl -A 'Mozilla/5.0 ... Chrome/120' \
      'https://fonts.googleapis.com/css2?family=Inter:wght@500&display=swap'
