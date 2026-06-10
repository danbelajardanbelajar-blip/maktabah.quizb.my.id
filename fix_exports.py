import os

def main():
    home_file = 'js/views/home.js'
    core_file = 'js/core/core.js'

    with open(home_file, 'r', encoding='utf-8') as f:
        home_content = f.read()

    # The block we want to remove from home.js
    block_start = home_content.find("// ── Recently Opened Books")
    block_end = home_content.find("export async function renderHome()")
    
    if block_start != -1 and block_end != -1:
        block = home_content[block_start:block_end]
        
        # Remove from home_content
        new_home_content = home_content[:block_start] + home_content[block_end:]
        with open(home_file, 'w', encoding='utf-8') as f:
            f.write(new_home_content)
        
        # Add 'export ' to the functions and consts
        block = block.replace("function getRecentlyOpened", "export function getRecentlyOpened")
        block = block.replace("function saveToRecentlyOpened", "export function saveToRecentlyOpened")
        block = block.replace("function recentBookCard", "export function recentBookCard")
        
        with open(core_file, 'a', encoding='utf-8') as f:
            f.write('\n\n' + block)
            
        print("Fixed exports!")
    else:
        print("Block not found!")

if __name__ == '__main__':
    main()
