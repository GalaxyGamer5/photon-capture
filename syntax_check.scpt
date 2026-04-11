function run(argv) {
  var app = Application.currentApplication()
  app.includeStandardAdditions = true;
  var fs = app.read(Path(argv[0]));
  // extract everything between <script> and </script> from line 150 to 1075
  var scriptContent = fs.split("<script>")[1].split("</script>")[0];
  try {
    eval("function trigger() {" + scriptContent + "}");
    return "Syntax OK";
  } catch (e) {
    return e.toString();
  }
}
