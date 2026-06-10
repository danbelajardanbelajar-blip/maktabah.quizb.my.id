import os
import glob

def main():
    view_files = glob.glob('js/views/*.js')
    
    for file_path in view_files:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
            
        if 'window.mobileFeedbackBanner' in content:
            new_content = content.replace('window.mobileFeedbackBanner', 'mobileFeedbackBanner')
            with open(file_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print(f"Updated {file_path}")

if __name__ == '__main__':
    main()
