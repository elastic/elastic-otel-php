#!/usr/bin/env python3
import os
import argparse
import re

new_header = """/*
 * Copyright Elasticsearch B.V. and/or licensed to Elasticsearch B.V. under one
 * or more contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright
 * ownership. Elasticsearch B.V. licenses this file to you under
 * the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */
"""

licensed_to_pattern = re.compile(r'Licensed to Elasticsearch B\.V\.', re.IGNORECASE)
header_start_pattern = re.compile(r'^(/\*|//)')
header_end_pattern = re.compile(r'\*/')
php_tag_pattern = re.compile(r'<\?php')

def find_header_end(lines):
    for i, line in enumerate(lines):
        if header_end_pattern.search(line):
            return i
    return -1

def find_header_start(lines):
    for i, line in enumerate(lines):
        if header_start_pattern.search(line):
            return i
    return -1

def find_php_tag(lines):
    for i, line in enumerate(lines):
        if php_tag_pattern.search(line):
            return i
    return -1


def replace_header(content, php_file):
    lines = content.splitlines()
    header_end_index = find_header_end(lines)
    header_start_index = find_header_start(lines)

    if header_start_index != -1:
        content = "\n".join(lines[0:header_start_index]) 
        content += "\n" + new_header

    if header_end_index != -1:
        content += "\n".join(lines[header_end_index + 1:])
    return content


def file_contains_license(filepath):
    with open(filepath, 'r') as file:
        in_comment_block = False
        for line in file:
            stripped_line = line.strip()
            if not stripped_line:
                continue
            if header_start_pattern.match(stripped_line):
                in_comment_block = True
            if in_comment_block:
                if licensed_to_pattern.search(stripped_line):
                    return True
                if header_end_pattern.search(stripped_line):
                    in_comment_block = False
    return False

def insert_license(content, php_file):
    if php_file:
        lines = content.splitlines()
        tag_start = find_php_tag(lines)
        print(tag_start)
        content = "\n".join(lines[0:tag_start + 1])
        content += "\n\n" + new_header
        content += "\n".join(lines[tag_start + 1:])
        return content
    else:
        return new_header + "\n" + content


def add_header_to_files(directory, extensions):
    for root, _, files in os.walk(directory):
        for filename in files:
            if any(filename.endswith("." + ext) for ext in extensions):
                filepath = os.path.join(root, filename)
                with open(filepath, 'r') as file:
                    content = file.read()

                php_file = filepath.endswith(".php")
                
                if file_contains_license(filepath):
                    content = replace_header(content, php_file)
                else:
                    content = insert_license(content, php_file)

                with open(filepath, 'w') as file:
                    file.write(content)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Add or replace header in files.')
    parser.add_argument('directory', type=str, help='The directory to process.')
    parser.add_argument('extensions', type=str, nargs='+', help='List of file extensions to process, e.g., cpp h hpp')

    args = parser.parse_args()
    add_header_to_files(args.directory, args.extensions)
