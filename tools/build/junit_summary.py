#!/bin/env python3
import argparse
import glob
import xml.etree.ElementTree as ET

def parse_arguments():
    parser = argparse.ArgumentParser(description="Parse JUnit XML test results and generate a summary.")
    parser.add_argument("--path-to-test-results", required=True, help="Path pattern to JUnit XML test result files. Supports wildcards (e.g., results/**/*.xml).")
    parser.add_argument("--header", required=True, help="The title of the summary output.")
    return parser.parse_args()

def parse_junit_xml(file):
    try:
        tree = ET.parse(file)
        root = tree.getroot()
        
        # Extract main test summary from the first-level testsuite (overall summary)
        main_suite = root.find("./testsuite[@name='']")
        if main_suite is None:
            main_suite = root.find("./testsuite")
        
        total_tests = int(main_suite.attrib.get("tests", 0))
        total_failures = int(main_suite.attrib.get("failures", 0))
        total_errors = int(main_suite.attrib.get("errors", 0))
        total_skipped = int(main_suite.attrib.get("skipped", 0))
        total_time = float(main_suite.attrib.get("time", 0))
        passed_tests = total_tests - total_failures - total_errors - total_skipped
        
        failures = []
        # Extract failures from nested test suites
        for ts in root.findall(".//testsuite[@name!='']"):
            for tc in ts.findall("testcase[failure]"):
                class_name = tc.attrib.get("classname", "Unknown")
                test_name = tc.attrib.get("name", "Unknown")
                file_path = tc.attrib.get("file", "Unknown")
                line = tc.attrib.get("line", "Unknown")
                failure_message = tc.find("failure").text.strip() if tc.find("failure") is not None else "Unknown"
                failure_message = failure_message.replace("\n", "<br>")  # Convert newlines to Markdown format
                failures.append((class_name, test_name, f"{file_path}:{line}", failure_message))
        
        return file, passed_tests, total_failures, total_skipped, total_errors, total_time, failures
    except Exception as e:
        print(f"Error processing {file}: {e}")
        return file, 0, 0, 0, 0, 0.0, []

def main():
    args = parse_arguments()
    files = glob.glob(args.path_to_test_results, recursive=True)
    
    print(f"## {args.header}\n")
    print("| Status | File | ✅ Passed | ❌ Failed | ⚠ Skipped | 🔍 Errors | ⏱ Time (s) |")
    print("|--------|------|---------|---------|---------|---------|---------|")
    
    all_failures = []
    for file in files:
        file, passed, failed, skipped, errors, total_time, failures = parse_junit_xml(file)
        status = "✅" if failed == 0 else "❌"
        print(f"| {status} | {file} | {passed} | {failed} | {skipped} | {errors} | {total_time:.3f} |")
        all_failures.extend(failures)
    
    if all_failures:
        print("<details>\n<summary>Failure Details</summary>\n")
        print("### Failure Details\n")
        print("| Test Class | Test Name | File:Line | Failure Message |")
        print("|------------|----------|----------|----------------|")
        for class_name, test_name, file_line, failure_message in all_failures:
            print(f"| {class_name} | {test_name} | {file_line} | {failure_message} |")
        print("\n</details>\n")


if __name__ == "__main__":
    main()
