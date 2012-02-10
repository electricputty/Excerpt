#Excerpt

## Usage:
Wrap this plugin around the {full_text} variable on your search results template.

i.e. {exp:zm_excerpt}{full_text}{/exp:zm_excerpt}

Alternatively you can wrap this plugin around any text, and supply words to highlight.

## Parameters:
### wrap=""

The html tag name (don't include the < > parts) to wrap around each highlighted word. The
default value is "strong".

### chunk_wrap=""

The html tag name (don't include the < > parts) to wrap around each relevant 'chunk'
extracted from the entire {full_text} value. The default value is "span". If supplied with
"none", nothing will be wrapped around each chunk.

### chunk_prepend=""

Data to prepend to each individual chunk returned. This data will be inserted inside the
chunk_wrap tags.

### chunk_append=""

Like the chunk_prepend parameter, but this inserts the data immediately before the closing
chunk_wrap tag. Defaults to '... '.

### output_wrap=""

The html tag name (don't include the < > parts) to wrap around the entire output. Defaults
to nothing.

### output_prepend=""

Data to insert in the output, before any chunks but after the opening output_wrap tag, if
supplied.

### output_append=""

Like the output_prepend parameter, but this will insert the data immediately before the
closing output_wrap tag if supplied.

### pre_chars=""

The number of characters to include before the highlighted term. Regardless of given value,
the pointer will not dissect whole words. The default value is "50". If given a value such
as "50-100" it will choose a random value between 50 and 100.

### post_chars=""

The number of characters to include after the hightlighted term. Regardless of given value,
the pointer will not dissect whole words. The default value is "50". If given a value such
as "50-100" it will choose a random value between 50 and 100.

### chunks=""

The number of relevant chunks to return. Values can be numeric or "all". The default value
is "3". Setting this parameter to all will return all relevant chunks found.

### keywords=""

Instead of acting dynamically, the plugin can be given the search term, or any term, via
this parameter.

### sort=""

Sort the chunks ascendingly or descendingly from the order in which they were found. values
are "asc" or "desc". Default is "asc".

### order=""

Order the chunks. Currently this parameter accepts only "random".

## Additional Notes:
The three wrap parameters (wrap, chunk_wrap, output_wrap) are tested against a list of
deemed 'safe' html tags. Should it fail this test it reverts back to the default tag for
that parameter. These defaults, as well as the safe list, can be edited. The variables are
located very near the top of the plugin file just below the line that looks like:
"class Zm_excerpt".