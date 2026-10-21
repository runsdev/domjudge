Installation of the team workstations
=====================================

To access DOMjudge, a team workstation needs nothing more than a modern
webbrowser, like a recent version of Chrome, Firefox or Edge. We do not
support legacy browsers like Internet Explorer. Of course the machine
also needs the appropriate development tools for the languages you want
to support.

The web browser needs to access the domserver via HTTP(S). It may be
convenient to teams if the URL of DOMjudge is set as the default homepage,
and if using a self-signed HTTPS certificate, that the browser is made
to trust this certificate.

.. _kiosk-mode:

Kiosk mode
----------
A team workstation can also run nothing but a web browser in kiosk mode:
full screen, without address bar, tabs or other applications. Teams then
write their code in the code editor of the team web interface instead of
uploading files. Set ``submit_methods`` to only ``paste`` in the
configuration settings to get the code editor without the file upload.

The code editor highlights the syntax, completes keywords, common library
functions and snippets (type ``main`` for a program template), and marks
syntax errors while typing for C, C++, C#, Go, Java, Python, Ruby, Rust
and shell scripts. The problem statement is shown next to the editor.
The code of each problem is kept as a draft in the browser, so it survives
reloads, logging out and restarting the browser.

To start Chromium or Google Chrome in kiosk mode, use ``misc-tools/dj_kiosk``
from the DOMjudge sources::

  dj_kiosk https://judge.example.org/team

This only allows connections to the DOMjudge server and keeps the browser
profile with the drafts in ``~/.domjudge-kiosk``. Set ``DJ_KIOSK_PROFILE``
to use another profile directory and ``DJ_KIOSK_BROWSER`` to choose the
browser; extra arguments are passed on to the browser. In kiosk mode,
:kbd:`Alt+Left` goes back, for example after opening an attachment.

With the ``team_lockdown`` setting, the team pages ask for full screen until
the browser is in full screen (as with ``dj_kiosk`` or :kbd:`F11`), and block
copy, cut, paste and dragging text, also within the code editor. Teams can
then only submit with the code editor, and not download, save or print
statements, samples or the problemset: they see Markdown statements next to
the code editor only, see :doc:`problem-format`, and the public pages show no
statements or samples.

The code editor can also run the code with input typed by the team, see
:ref:`run-code`.

Let teams log in automatically based on their IP address (see
:ref:`authentication`, ``auth_methods`` and ``ip_autologin``), so the
workstation can start the browser directly after booting.

For a stricter lockdown, also install a Chromium policy, on Linux in
``/etc/chromium/policies/managed/`` (Chromium) or
``/etc/opt/chrome/policies/managed/`` (Google Chrome), for example::

  {
    "URLBlocklist": ["*"],
    "URLAllowlist": ["judge.example.org"],
    "DeveloperToolsAvailability": 2,
    "DownloadRestrictions": 3,
    "AllowFileSelectionDialogs": false,
    "PasswordManagerEnabled": false
  }

Firefox has a ``--kiosk`` option as well and can be restricted in a similar
way with an enterprise ``policies.json``.

.. _submit_client_requirements:

Command line submit client
--------------------------
DOMjudge comes with a command line submit client which makes it really
convenient for teams to submit their solutions to DOMjudge.

In order to use the submit client, you need Python, the python requests
library and optionally the python magic library installed on the team's
workstation. To install this on Debian-like distributions::

  sudo apt install python3 python3-requests python3-magic

Or on RedHat/CentOS/Fedora::

  sudo yum install python3 python3-requests python3-magic

You can now copy this client from ``submit/submit`` to the workstations.

The submit client needs to know the base URL of the domserver where it should
submit to. You have three options to configure this:

* Set it as an environment variable called ``SUBMITBASEURL``, e.g. in
  ``/etc/profile.d/``.
* Modify the ``submit/submit`` file and set the variable of ``baseurl``
  at the top.
* Let teams pass it using the ``--url`` argument.

Note that the environment variable overrides the hardcoded variable at
the top of the file and the ``--url`` argument overrides both other options.

The submit client will need to know to which contest to submit to. If there
is only one active contest, that will be used. If not, you have two options
to configure this:

* Set it as an environment variable called ``SUBMITCONTEST``, e.g. in
  ``/etc/profile.d/``.
* Let teams pass it using the ``--contest`` argument.

Note that the ``--contest`` argument overrides the environment variable.

In order for the client to authenticate to DOMjudge, credentials can be
pre-provisioned in the file ``~/.netrc`` in the user's homedir, with example
content::

  machine yourhost.example.edu login user0123 password Fba^2bHzz

See the `netrc manual page`_ for more details. You can run ``./submit --help``
to inspect its configuration and options.

Rebuilding team documentation
-----------------------------

The source of the team manual can be found in ``doc/manual/team.rst``.
The team manual can incorporate specific settings of your environment,
most notably the URL of the DOMjudge installation. To achieve this,
rebuild the team manual *after* configuration of the system.

.. note::

  A prebuilt team manual is included, but this contains
  default/example values for site-specific configuration settings such
  as the team web interface URL and judging settings such as the memory
  limit. We strongly recommend rebuilding the team manual to include
  site-specific settings and also to revise it to reflect your contest
  specific environment and rules.


When DOMjudge is configured and site-specific configuration set,
the team manual can be generated with the command ``make docs``.
The following should do it on a Debian-like system::

  sudo apt install python3-sphinx python3-sphinx-rtd-theme fontconfig python3-yaml
  cd <DOWNLOAD_PATH>/doc/
  make docs

The resulting manual will then be found in the ``team/`` subdirectory.

.. _netrc manual page: https://ec.haxx.se/usingcurl/usingcurl-netrc
