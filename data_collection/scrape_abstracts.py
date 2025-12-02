import pandas as pd
import requests
from bs4 import BeautifulSoup
import re
import csv

# Change this variable to the name of the sheet that is to be scraped
INPUT_CSV = "temp_input.csv"

# This is the name of the file that is to be created
OUTPUT_CSV = "temp_output.csv"


# Function: clean_text(string -> string)
# Purpose: clean_text takes a string then removes control chars and extra spaces
def clean_text(s):
    if not isinstance(s, str):
        return s
    s = re.sub(r'[\x00-\x1F\x7F]', ' ', s)
    s = re.sub(r'\s+', ' ', s)
    return s.strip()

# Panda is reading the csv file then storing it into a dataframe to be parsed through
df = pd.read_csv(INPUT_CSV)

# Checks to see if there are URLs to be scraped for abstracts
if 'URL' not in df.columns:
    raise ValueError("CSV must have a column named 'URL'")

# Create a blank list to input all scraped abstracts
abstracts = []

# "URL" is the base name for the column that is to be used. 
# It can be changed if your column is named something else.
for url in df['URL']:

    # If there is no URL in that row, it adds "NO URL" to the list
    # to keep the general order of the csv file.
    if not url or str(url).strip() == "":
        abstracts.append("NO URL")
        continue


    try:
        # Send an HTTP GET request to the URL, if the server does not respond within
        # 10 seconds, then it timeout
        response = requests.get(url, timeout=10)

        # Prevents processing bad responses, 200-299 is good and 400-500 raises an error
        response.raise_for_status()

        # Says what type of content the server returned
        content_type = response.headers.get("Content-Type", "").lower()

        # If the content_type is not html then it appends that info to the list
        if "text/html" not in content_type:
            abstracts.append(clean_text(f"NON-HTML CONTENT: {content_type}"))
            continue

        # BeutifuelSoup is used to turn the content_type into a structured object to
        # be parsed through
        soup = BeautifulSoup(response.text, 'html.parser')

        # Searches for the description and citation_abstract tag
        meta_desc = soup.find('meta', attrs={'name': 'description'}) or \
                    soup.find('meta', attrs={'name': 'citation_abstract'})
                    

        # Checks if there is a valid tag or not. If there is it is cleaned, 
        # then appended to the list.
        if meta_desc and meta_desc.get('content'):
            abstracts.append(clean_text(meta_desc['content']))
            continue

        # Finds all the paragraphs in the html
        paragraphs = soup.find_all('p')
        # If there are pargraphs it will take the longest then append it to the list.
        # If not then "NO ABSTRACT FOUND" is appended to the list
        if paragraphs:
            longest_p = max(paragraphs, key=lambda p: len(p.get_text(strip=True)))
            text = longest_p.get_text(strip=True)

            abstracts.append(clean_text(text) if text else "NO ABSTRACT FOUND")
        else:
            abstracts.append("NO ABSTRACT FOUND")
    # Catch any error within try:, then appends the error to the list
    except Exception as e:
        abstracts.append(clean_text(f"ERROR: {str(e)}"))

# Adds a new column to the dataframe with the list
df['Abstract'] = abstracts

# Creates a new csv file, adding quotes to avoid corrupted characters
df.to_csv(OUTPUT_CSV, index=False, quoting=csv.QUOTE_ALL)

# Confirms the script ran
print(f"Saved results to {OUTPUT_CSV}")