# Moodle + CodeRunner on OpenShift

It is possible to build a working version of Moodle + CodeRunner for free
on Red Hat's OpenShift framework. I've done this several times as a way
of checking the CodeRunner install instructions. If you use the free
version, with at most 3 gears for both Moodle and the Jobe sandbox server,
the resulting server will be only low performance and isn't recommended
for production use, but it does give you a simple way to trial Moodle + CodeRunner.

This document explains how to set up such a prototype system.

## Setting up a Moodle server

 1. Set yourself up with an account on [OpenShift](https://openshift.redhat.com).
    Make sure you're logged in to that account in your browser.
 1. Go to the [Moodle 2.8+ Quickstart page](https://hub.openshift.com/quickstarts/65-moodle-2-8-1)
    and click *deploy*. After a couple of minutes you should find you have
    a working Moodle system. It will be the latest version (3.0.3 at the time
    of writing) not 2.8.
 1. Click "Continue to the application overview page" and record your administrator
    name and password somewhere.
 1. Click "Want to log in to your application?", copy the ssh command you're
    given and run that command to login to your new machine.
 1. `cd app-root/repo/php` which puts you in the Moodle home directory.
 1. Get the latest version of the adaptive\_adapted\_for\_coderunner question behaviour with the
    command
        git clone git://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner.git question/behaviour/adaptive_adapted_for_coderunner
 1. Get the latest version of the CodeRunner question type with the command
        git clone git://github.com/trampgeek/moodle-qtype_coderunner.git question/type/coderunner
 1. Start your server from the GUI on the openshift webpage, login using the
    administrator credentials you were given, fill out the various site info.
 1. You're now looking at the Moodle home page. Choose Site administration >
    notifications and you should be told that the two plugins you just
    added (the behaviour and the CodeRunner question type) need to be updated.
    Do it!

That's it. You should now have a working Moodle/CodeRunner server, using the
default Jobe sandbox provided by the University of Canterbury.

As explained in
the documentation the default Jobe server is not for production use.
You now either need to set up
your own Jobe server, downloaded from [here](https://github.com/trampgeek/jobe)
or you can ask me, [Richard Lobb](mailto:richard.lobb@canterbury.ac.nz), for access
to that same server using a key that does not limit the number of runs per hour.
Such keys will be made available to educational institutions (particularly New Zealand ones),
subject to sufficient resources being available and with no quality of service or
longer term commitments.

**IMPORTANT**: CodeRunner is designed to
work only in an Adaptive Mode so when previewing questions
you must set the *How questions behave* dropdown
under *Attempt Options* to *Adaptive mode*. If you fail to do this, you'll
Perhaps an empty answer, or question behaviour not set to Adaptive Mode?". When setting
quizzes using CodeRunner questions, you should run the entire quiz in Adaptive
Mode, again using the 'Question behaviour' dropdown under Quiz Settings. If you
are using a Moodle server set up specifically to run CodeRunner questions, it is
recommended that you set the default question behaviour for the whole site to
Adaptive Mode.

## Setting up a Jobe server on OpenShift

A standard Jobe server runs all the submitted jobs using the
[DOMJudge](http://domjudge.org) *runguard* program. This tightly controls the
resource usage of the submitted job, protecting the server against programs
that loop, consume excessive memory, fork too many processes, etc. However,
runguard requires sudo (i.e., superuser) access which is not available under
OpenShift. It's thus not possible to run standard Jobe under OpenShift.

However blah blah

1. Create a new PHP5.4 OpenShift application
1. Add the Python3.5 cartridge:
https://raw.githubusercontent.com/Grief/openshift-cartridge-python-3.5/master/metadata/manifest.yml
1. cd app-root/repo
1. git clone https://github.com/trampgeek/jobe.git
1. cd jobe
1. git checkout unsafe
1. *** HACK _ FIXEME *** change the executable path in getExecutablePath and also
  the token python3 in the compile command to something like
 /var/lib/openshift/56d9fe842d527137ea0000e3/python/usr/bin/python3
1. mkdir $OPENSHIFT_DATA_DIR/files
1. cd $OPENSHIFT_REPO_DIR/jobe
1. ln -s /var/lib/openshift/56d9fe842d527137ea0000e3/app-root/data/files .
1. cd
1. wget https://bootstrap.pypa.io/get-pip.py ; python3 get-pip.py
1.