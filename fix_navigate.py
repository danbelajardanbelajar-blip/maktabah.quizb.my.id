import os

def main():
    core_file = 'js/core/core.js'
    main_file = 'js/main.js'

    with open(core_file, 'a', encoding='utf-8') as f:
        f.write("\nexport let navigate = () => {};\nexport function setNavigate(fn) { navigate = fn; }\n")

    with open(main_file, 'r', encoding='utf-8') as f:
        main_js = f.read()

    main_js = main_js.replace("Core.navigate = function(path, push = true) {", "function localNavigate(path, push = true) {")
    main_js = main_js.replace("window.navigate = Core.navigate;", "Core.setNavigate(localNavigate);\nwindow.navigate = localNavigate;")
    
    # Also fix the event listeners in main.js
    main_js = main_js.replace("Core.navigate(location.pathname", "localNavigate(location.pathname")
    main_js = main_js.replace("Core.navigate(route);", "localNavigate(route);")

    with open(main_file, 'w', encoding='utf-8') as f:
        f.write(main_js)

    print("Fixed navigate!")

if __name__ == '__main__':
    main()
