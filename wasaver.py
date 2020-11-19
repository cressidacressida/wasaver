#!/usr/bin/python

# wasaver
# Copyright (C) 2020  cressidacressida
# 
# This file is part of wasaver.
# 
# wasaver is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
# 
# wasaver is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with wasaver.  If not, see <https://www.gnu.org/licenses/>.

import os, sys, getopt, string, re
from datetime import datetime
import pymysql.cursors

sys.path.append(os.path.dirname('WebWhatsapp-Wrapper/'))
from webwhatsapi import WhatsAPIDriver

default_config_file = re.sub(r'^(\./)?(.+)\.py$', r'\2.conf', sys.argv[0])
date_format = "%d/%m/%Y"

def usage():
    print("Usage: " + sys.argv[0] + """ [OPTION]... [CONTACT]
Download WhatsApp chat with CONTACT.

Mandatory arguments to long options are mandatory for short options too.
  -c, --config=FILE   use FILE as config file
                        (default is """ + default_config_file + """)
  -R, --recreate      clean the database before downloading
  -t, --till=DATE     get earlier messages till DATE
                        (date format is """ + date_format + """)
  -h, --help          display this help and exit
""")

def create_database(database_name, connection, recreate):
    with connection.cursor() as cursor:
        if recreate:
            cursor.execute(f"DROP DATABASE IF EXISTS {database_name}")
        cursor.execute(f"CREATE DATABASE IF NOT EXISTS {database_name}")
        cursor.execute(f"USE {database_name}")
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS messages (
  id              VARCHAR(150) NOT NULL,
  datetime        DATETIME NOT NULL,
  sender          VARCHAR(150) NOT NULL,
  quoting         VARCHAR(150),
  forwarded       BOOL,
  revoked         BOOL,
  PRIMARY KEY     (id)
);
''')
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS text_messages (
  id              VARCHAR(150) NOT NULL,
  text            TEXT NOT NULL,
  PRIMARY KEY     (id),
  FOREIGN KEY     (id) REFERENCES messages (id)
);
''')
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS audio_messages (
  id              VARCHAR(150) NOT NULL,
  filename        VARCHAR(150) NOT NULL,
  PRIMARY KEY     (id),
  FOREIGN KEY     (id) REFERENCES messages (id)
);
''')
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS media_messages (
  id              VARCHAR(150) NOT NULL,
  media_type      VARCHAR(20) NOT NULL,
  filename        VARCHAR(150) NOT NULL,
  caption         TEXT,
  PRIMARY KEY     (id),
  FOREIGN KEY     (id) REFERENCES messages (id)
);
''')
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS geo_messages (
  id              VARCHAR(150) NOT NULL,
  latitude        VARCHAR(20) NOT NULL,
  longitude       VARCHAR(20) NOT NULL,
  PRIMARY KEY     (id),
  FOREIGN KEY     (id) REFERENCES messages (id)
);
''')
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS vcard_messages (
  id              VARCHAR(150) NOT NULL,
  contact         VARCHAR(300) NOT NULL,
  PRIMARY KEY     (id),
  FOREIGN KEY     (id) REFERENCES messages (id)
);
''')
        cursor.execute('''\
CREATE TABLE IF NOT EXISTS notification_messages (
  id              VARCHAR(150) NOT NULL,
  notification    VARCHAR(150) NOT NULL,
  PRIMARY KEY     (id),
  FOREIGN KEY     (id) REFERENCES messages (id)
);
''')
    connection.commit()

def main():
    # command line arguments handling
    try:
        opts, args = getopt.getopt(sys.argv[1:], "c:hRt:", ["config=", "help", "recreate", "till="])
    except getopt.GetoptError as err:
        print(err)
        usage()
        sys.exit(2)
    config_file = default_config_file
    recreate = False
    start_date = None
    for o, a in opts:
        if o in ("-c", "--config"):
            config_file = a
        elif o in ("-h", "--help"):
            usage()
            sys.exit()
        elif o in ("-R", "--recreate"):
            recreate = True
        elif o in ("-t", "--till"):
            start_date = datetime.strptime(a, date_format)
            print(start_date.strftime('%s'))
        else:
            assert False, "unhandled option"

    # parse config file
    required_options = ("db_host", "db_user", "db_password")
    option_list = ("contact_name", "username", "firefox_profile") + required_options
    options = dict.fromkeys(option_list, None)
    if not os.path.isfile(config_file):
        print(sys.argv[0] + f": cannot find config file '{config_file}'")
        sys.exit(1)
    else:
        with open(config_file, 'r') as file:
            for line in file:
                line = line.strip()
                if re.match('^#', line):
                    continue
                for option in options:
                    regexp = '^' + option + ' *= *'
                    if re.match(regexp, line):
                        options[option] = re.sub(regexp, '', line)
                        if re.match('^".*"$', options[option]) or re.match(r"^'.*'$", options[option]):
                            options[option] = options[option][1:-1]
    for option in options:
        if option in required_options and (options[option] is None or options[option] is ""):
            print(sys.argv[0] + f": missing required option '{option}' in config file '{config_file}'")
            sys.exit(1)

    # determining contact name
    if len(args) == 0:
        if options["contact_name"] is None or options["contact_name"] is "":
            print(sys.argv[0] + ": argument CONTACT required")
            sys.exit(1)
        else:
            contact_name = options["contact_name"]
    elif len(args) > 1:
        print(sys.argv[0] + ": too many arguments given")
        sys.exit(1)
    elif args[0] is "":
        print(sys.argv[0] + ": argument CONTACT is null")
        sys.exit(1)
    else:
        contact_name = args[0]

    contact_safe_name = "".join(c for c in contact_name if c in string.ascii_letters or c in string.digits or c == '_')
    database_name = "chat_" + contact_safe_name
            
    # create database and tables
    connection = pymysql.connect(host = options["db_host"], user = options["db_user"], password = options["db_password"], charset="utf8mb4", autocommit=False)
    create_database(database_name, connection, recreate)

    # get messages
    username = options["username"]
    firefox_profile = options["firefox_profile"]
    path = "media_" + contact_safe_name
    if not os.path.exists(path):
        os.makedirs(path)

    driver = WhatsAPIDriver(username = options["username"], profile = options["firefox_profile"])
    driver.wait_for_login()

    chat = driver.get_chat_from_name(contact_name)
    if start_date is None:
        chat.load_all_earlier_messages()
    else:
        print("qui")
        chat.load_earlier_messages_till(start_date)
    messages = chat.get_messages(True, True)

    # process messages
    for message in messages:
        with connection.cursor() as cursor:
            cursor.execute("SELECT id FROM messages WHERE id = %s", (message.id,))
            present = cursor.rowcount > 0
        has_media = callable(getattr(message, "save_media", None))
        if present and has_media:
            with connection.cursor() as cursor:
                cursor.execute('''
SELECT filename FROM (SELECT id, filename FROM audio_messages UNION
                      SELECT id, filename FROM media_messages) AS t1
WHERE id = %s''', (message.id,))
                filename = cursor.fetchone()[0]
                media_is_present =  os.path.isfile(path + '/' + filename)

        # print summary
        print("------------------------------------------------------")
        print(message.id)
        print(message)
#        print(type(message).__name__)
        if present:
            print("    Message found in database")
            if has_media:
                if media_is_present:
                    print(f"    Media '{path}/{filename}' found")
                else:
                    print("    Error: media not found")
                    connection.close()
                    driver.close()
                    sys.exit(1)

        # populate database
        if not present:
            if has_media:
                print("    Downloading media...", end = '')
                message.save_media(path, True);
                print(" done")
            with connection.cursor() as cursor:
                cursor.execute("INSERT INTO messages VALUES (%s, %s, %s, %s, %s, %s)",
                               (message.id, message.timestamp, message.sender.get_safe_name(), message.quoting, message.forwarded,
                                True if message.type == "revoked" else False))
                if type(message).__name__ == "Message":
                    if message.type != "revoked":
                        cursor.execute("INSERT INTO text_messages VALUES (%s, %s)",
                                       (message.id, message.content))
                elif type(message).__name__ == "MMSMessage":
                    cursor.execute("INSERT INTO audio_messages VALUES (%s, %s)",
                                   (message.id, message.filename))
                elif type(message).__name__ == "MediaMessage":
                    cursor.execute("INSERT INTO media_messages VALUES (%s, %s, %s, %s)",
                                   (message.id, message.type, message.filename, message.caption))
                elif type(message).__name__ == "GeoMessage":
                    cursor.execute("INSERT INTO geo_messages VALUES (%s, %s, %s)",
                                   (message.id, message.latitude, message.longitude))
                elif type(message).__name__ == "VCardMessage":
                    cursor.execute("INSERT INTO vcard_messages VALUES (%s, %s)",
                                   (message.id, message.contacts))
                elif type(message).__name__ == "NotificationMessage":
                    cursor.execute("INSERT INTO notification_messages VALUES (%s, %s)",
                                   (message.id, message.readable))
                else:
                    print("    Error: message not recognized")
                    cursor.execute("DELETE FROM messages WHERE id = %s", (message.id,))
                    connection.commit()
                    connection.close()
                    driver.close()
                    sys.exit(1)
            connection.commit()

    connection.close()
    driver.close()

if __name__ == "__main__":
    main()
