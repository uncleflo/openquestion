# Open Question

A questionnaire with open and transformable questions

## Description

This is a very quick and small project, to quickly create a configureable questionnaire on a website.

The goal is to embed this questionnaire into an existing website without altering urls, and only commit POSTs for every action done.

The goal is also for users to post their own answers in case the admin did not provide spot-on answers.

Admin can log in using a ?admin=PASSHERE, and logout using anything else. A config allows configuring the admin password, whether visitors can alter their votes or not, and howmany questions they can add.

## Unique features

- The public visitors can add their own answers to the questions. This allows the questionnaire to be adjusteable to what the users think, rather than to what the site admin thinks the users think.
- Very simple, no admin dashboard.
- Ideal for quick investigations.

## Installation

Just add include_once("openquestion.php"); somewhere inside your php file, where you wish to display the questionnaire, and alter the config.php to connect to a database. Tables can be prefixed.

## Todo

Currently, the openquestion logic relies on bootstrap. This should not have templates, but could be hard to solve..