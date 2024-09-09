<?php

class TestClass {
  function userspace($arg1, $arg2, $arg3) {
    echo "* userspace() body start.\n";
    echo "args:\n";
    var_dump(func_get_args());
    echo "* userspace() body end\n";
    return "userspace_rv";
  }
}
