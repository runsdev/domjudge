<img align="right" width="96px" alt="DOMjudge-logo" src="./doc/logos/DOMjudgelogo-with-white-background.png">

DOMjudge
--------
[![pipeline status](https://gitlab.com/DOMjudge/domjudge/badges/main/pipeline.svg)](https://gitlab.com/DOMjudge/domjudge/commits/main)
[![Coverity Scan Status](https://img.shields.io/coverity/scan/671.svg)](https://scan.coverity.com/projects/domjudge)
[![CodeQL alerts](https://github.com/DOMjudge/domjudge/actions/workflows/codeql-analysis.yml/badge.svg?branch=main&event=push)](https://github.com/DOMjudge/domjudge/actions/workflows/codeql-analysis.yml)

About this fork
---------------
This fork of [DOMjudge](https://github.com/DOMjudge/domjudge) is for
programming contests and course exams on locked-down workstations: teams write,
run and submit their code in the browser, without an IDE or a compiler of their
own, and cannot copy and paste, use AI tools or visit other websites than
DOMjudge and the C++ reference.

It adds to DOMjudge:

- **A code editor for teams**, with syntax highlighting, completions and
  snippets, and syntax errors marked while typing for C, C++, C#, Go, Java,
  Python, Ruby, Rust and shell scripts. The problem statement is shown next to
  it, and the code of each problem is kept as a draft in the browser.
- **Running code** on the judgehosts with input typed by the team and the
  limits of the problem, without submitting it (setting `allow_test_runs`, on
  by default).
- **Markdown problem statements** (`problem_statement/problem.md` in a problem
  package) with LaTeX math, shown in the browser with the samples below them.
- **Lockdown** (setting `team_lockdown`): teams submit with the code editor
  only; copy, cut, paste and dragging text are blocked, also within the editor;
  only text in the editor and input fields can be selected; the context menu of
  the browser, with its search and AI entries, does not open; and statements,
  samples and the problemset cannot be downloaded, saved or printed. With
  `team_lockdown_fullscreen` as well, the team pages also require full screen.
- **Locked-down workstations**: `misc-tools/dj_kiosk` starts Chromium or Google
  Chrome in kiosk mode, and `misc-tools/dj_seb_config` writes a
  [Safe Exam Browser](https://safeexambrowser.org) configuration, see below.
- **A toy contest** to try it all, with one problem per topic of an
  introductory C++ course, in English and in Indonesian:
  `misc-tests/toy-contest/make.py`.

The "Kiosk mode" section of the manual (`doc/manual/install-workstation.rst`)
and the `ChangeLog` have the details; `misc-tests/e2e-editor-kiosk` tests it all
end to end in a browser.

### Running a locked-down contest

1. Turn on `team_lockdown` in the configuration settings of the jury interface.
2. Give the problems Markdown statements; in lockdown, teams see no other ones.
3. List the C++ reference in `etc/docs.yaml`, so teams find it under *Docs*:

       - name: C++ reference
         link: https://en.cppreference.com/w/cpp

4. Start the team workstations with Safe Exam Browser or `dj_kiosk`. Without
   either, also turn on `team_lockdown_fullscreen`.

### Safe Exam Browser

`misc-tools/dj_seb_config` writes a configuration for Safe Exam Browser for
Windows: DOMjudge in full screen on a desktop of its own, no other
applications, no clipboard, and no other websites than DOMjudge and
cppreference.com. Its toolbar has back, forward and reload buttons, and a home
button back to DOMjudge. Drafts in the code editor survive restarts.

    export DJ_SEB_QUIT_PASSWORD=...   # optional, the password to quit it
    misc-tools/dj_seb_config https://judge.example.org/team > domjudge.seb

Opening the file on a workstation with Safe Exam Browser starts it with this
configuration. Examples, made with `dj_seb_config`, are in
[`doc/examples/seb`](doc/examples/seb):

- [`localhost.seb`](doc/examples/seb/localhost.seb) for the development setup
  of `docker-compose.yml` at http://localhost:12345, without a quit password.
- [`judge.example.org.seb`](doc/examples/seb/judge.example.org.seb) for a
  contest at https://judge.example.org, with the quit password `change-me`.
  Make your own with the URL of your DOMjudge and a password of your own.

Upstream DOMjudge
-----------------

This is the Programming Contest Jury System "DOMjudge" version 10.0.0DEV

DOMjudge is a system for running a programming contest, like the ICPC regional
and world championship programming contests. It can also be used to run
IOI-style (partial scoring) contents.

Installation
------------
We provide [stable releases](https://www.domjudge.org/download) and [daily
snapshots](https://www.domjudge.org/snapshot/) of the latest code.
Bootstrapping from git is an advanced use case and mostly needed for
development. Installing DOMjudge is almost never done via a `git clone`, except
for development use cases or testing the latest code. 

Documentation
-------------

For more information on installation and requirements see the
documentation under the doc/manual directory. There is an HTML
version of the documentation available, prebuilt from reStructuredText
sources.

There's also team documentation available, prebuilt in PDF format from
reStructuredText sources, but it contains default/example settings.
To include the correct settings for your local environment, DOMjudge
has to be properly configured first, as parts of the configuration are
used in it (e.g. the URL to the team interface of DOMjudge). For more
information, see the documentation.

All documentation is also available online at the DOMjudge homepage:
	https://www.domjudge.org/documentation

Please note that this documentation is from the latest stable
release and thus might not apply to your version.

A fresh copy of the repository source tree must first be bootstrapped,
generating the configure script and documentation. This can be done
by running `make dist`, see the online documentation, section
"Development" for more details.

There is a wiki which collects other pieces of information about
specific configurations or integrations:
https://github.com/DOMjudge/domjudge/wiki

Copyright & Licensing
---------------------

DOMjudge is Copyright (c) 2004 - 2026 by the DOMjudge developers and
all respective contributors. The current DOMjudge developers are Jaap
Eldering, Nicky Gerritsen, Keith Johnson, Thijs Kinkhorst, Mart
Pluijmaekers, Michael Vasseur and Tobias Werth; see the manual for
a complete list of contributors.

DOMjudge, including its documentation, is free software; you can
redistribute it and/or modify it under the terms of the GNU General
Public License as published by the Free Software Foundation; either
version 2, or (at your option) any later version. See the file
COPYING.

Additionally, parts of this system are based on other programs, which
are covered by other copyrights. This will be noted in the files
themselves and these copyrights/attributions can also be found in the
DOMjudge manual.

The default validator from the Kattis problemtools package is
included, licensed under the MIT licence, see COPYING.MIT.

The M4 autoconf macros are licensed under all-permissive and GPL3+
licences; see the respective files under m4/ for details.

The DOMjudge tarball ships external library dependencies in the
webapp/vendor and webapp/node_modules directories. The libraries
shipped under webapp/vendor are covered by their individual licenses
as specified in the file composer.lock. The libraries shipped under
webapp/node_modules are covered by the licenses documented in
webapp/LICENSES.txt.

Contact
-------

The DOMjudge homepage can be found at:
https://www.domjudge.org/

Announcements of new releases are sent to our low volume announcements
mailinglist. Subscription to this list is done via
https://www.domjudge.org/mailman/postorius/lists/domjudge-announce.domjudge.org/

The developers can be reached through the mailinglist
domjudge-devel@domjudge.org. You need to be subscribed before
you can post. Information, subscription and archives are available at:
https://www.domjudge.org/mailman/postorius/lists/domjudge-devel.domjudge.org/

DOMjudge has a Slack workspace where a number of developers and
users of DOMjudge linger. More information can be found at
https://www.domjudge.org/chat

Special thanks
--------------

[Jetbrains](https://www.jetbrains.com) for providing a free license for their
  IDEs.

[<img src="https://www.jetbrains.com/company/brand/img/jetbrains_logo.png" height="75">](https://www.jetbrains.com)

[Blackfire](https://blackfire.io) for providing a free license for their
  profiling tool.

[<img src="https://platform.sh/blackfire-logos/full/color/BFlogo_aPSHproduct_Color_H.png" height="75">](https://blackfire.io)
