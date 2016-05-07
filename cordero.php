<?php
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////3-16-16
function searchSuggestions($search_info)
{
    //$search_info = 'escherichia coli infection';//sample
    $stopwords = array('the', 'a', 'an', 'of', 'and', 'to', 'with', 'without', 'due', 'within', 'for', 'or', 'in', 'as');
    
    $searchwords=explode(" ", trim(strtolower(preg_replace("#[[:punct:]]#", "", $search_info))));

    if (count($searchwords) < 2)//INDEPENDENT COMBINATION SEARCH
    {
        $file = file_get_contents('../lib/counted_words.csv' , true);  
        $words=explode("\n", $file);   
        $linearray = array();
        foreach ($words as $entry) 
        {
            $line = explode(" ", $entry);
            array_push($linearray, $line);
        }
        
        echo 'Independent results displayed because number of search words is ' . count($searchwords) . '<br><br>';
        $library = fillLibrary($linearray, $stopwords, $searchwords);
        //echo 'Here is the library:<br>';
        //var_dump($library);
    
        $searchtokens = processSearchWords($searchwords, $stopwords);
        
        $associatedcombined = independent($searchtokens, $library);
        echo 'First 20 suggestions and frequencies:<br>';
    
        //printSuggestions($associatedcombined, $searchtokens);
        return $associatedcombined;
    }
    else//FULL BAYES SEARCH
    {
        echo "Bayes' suggestions displayed because number of search words is " . count($searchwords) . '<br><br>';
        $searchtokens = processSearchWords($searchwords, $stopwords);
        $newsearchlines = dependent($searchtokens);

        $suggestions = wordFrequency($newsearchlines, $searchtokens, $stopwords);
        
        echo 'Suggestions and frequencies:<br>';
        //printSuggestions($suggestions, $searchtokens);
        return $suggestions;
    }
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    


///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function printSuggestions($array, $searchthings)
{
    $printamount = 0;
    foreach(array_keys($array) as $key)
    {
        foreach(array_unique($searchthings) as $word)
            echo $word . ' ';
        echo $key . '<br>';
        $printamount++;
        if($printamount == 10)
            break;
    }
    echo '<br>';
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function wordFrequency($newsearchlines, $searchwords, $stopwords)//builds suggestions associative array, then sorts it
{
    $suggestions = array();
    foreach($newsearchlines as $newsearchline)
    {
        foreach($newsearchline as $w)
        {
            $w = trim(strtolower(preg_replace('#[[:punct:]]#', '', $w)));
            
            foreach($stopwords as $stopword)//don't count stopwords
            {
                if (strcasecmp($w, $stopword) == 0)
                    goto nextword;
            }

            foreach($searchwords as $searchword)//don't coun't words being searched
            {
                if ($w == $searchword)
                    goto nextword;
            }
            
            if (array_key_exists($w, $suggestions))
                $suggestions[$w] += 1;
            else
                $suggestions[$w] = 1;
            
            nextword:
        }
    }
    arsort($suggestions);
    return $suggestions;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


    
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////   
function fillLibrary($linearray, $stopwords, $searchwords)//populates array of array of numbers
{
    $library = array();
    foreach($linearray as $line)
    {
        $added_key = false;
        $read_word = true;
        $addedcount = 0;

        foreach ($line as $w)
        {
            $w = trim(strtolower(preg_replace("#[[:punct:]]#", "", $w)));

            if ($added_key)
            {
                if ($read_word)
                {
                    $word = $w;
                    $addthis = true;
                    foreach($stopwords as $stopword)
                    {
                        if (strcasecmp($word, $stopword) == 0)
                        {
                            $addthis = false;
                            break;
                        }
                    }
                    $read_word = false;
                }
                else
                {
                    if ($addthis)//&& !preg_match("/{.*}/",$w) not sure what that does
                    {
                        $library[$key][$word] = (int)$w;
                        $addedcount++;
                    }
                    if ($addedcount == 20)
                        goto nextline;

                    $read_word = true;
                }
            }
            else
            {
                $usethisline = false;
                foreach($searchwords as $searchword)//check first word
                {
                    if ($w == $searchword)
                        $usethisline = true;
                }
                if(!$usethisline)
                    goto nextline;

                $key = $w;
                $added_key = true;
            }
        }
        nextline:
    }
    foreach(array_keys($library) as $key)
        arsort($library[$key]);
    
    return $library;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    

    
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function linesof($file)//searchword1
{
    $file = file_get_contents($file , true);  
    $words=explode("\n", $file);   
    $linearray = array();
    foreach ($words as $entry) 
    {
        $entry = trim(strtolower(preg_replace("#[[:punct:]]#", "", $entry)));
        $line = explode(" ", $entry);
        array_push($linearray, $line);
    }
    return $linearray;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    
    
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function processSearchWords($searchwords, $stopwords)
{
    $searchtokens=array();//process the search words
    foreach($searchwords as $searchword)
    {
        $searchword = trim(strtolower(preg_replace("#[[:punct:]]#", "", $searchword)));
        foreach($stopwords as $stopword)
        {
            if (strcasecmp($searchword, $stopword) == 0)
            goto skip;
        }
        array_push($searchtokens,$searchword);//if(!preg_match("/{.*}/",$searchword))

        skip:
    }
    return $searchtokens;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function independent($searchtokens, $library)
{
    $associatedcombined = array();//associatedcombined list
    foreach(array_unique($searchtokens) as $token)
    {
        if (array_key_exists($token, $library))
        {
            foreach(array_keys($library[$token]) as $key)//for each word associated with the search token
            {
                if (in_array($key, array_map('strtolower', array_unique($searchtokens))))//do not add key to combined array if it is one of the search tokens
                    goto skipkey;
                //else if ($library[$token][$key] < 2)//threshold for adding to combined array
                //    break;
                else if (array_key_exists($key, $associatedcombined))//if word has been added to combined array
                    $associatedcombined[$key] += $library[$token][$key];
                else
                    $associatedcombined[$key] = $library[$token][$key];

                skipkey:
            }
        }
    }
    arsort($associatedcombined);    
    return $associatedcombined;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
    
   
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function dependent($searchwords)
{
    $testlines = linesof('../lib/diagProcessed.csv');//Leon csv 2
    $test = linesof('../lib/diag.csv');//diag.csv
    $oldsearchlines = getFirstWordLines($testlines, $test, $searchwords);
    
    for ($i = 1; $i < count($searchwords); $i++)
    {
        $newsearchlines = array();   
        foreach($oldsearchlines as $oldsearchline)
        {
            if (in_array($searchwords[$i], $oldsearchline))//searchword2/searchword3/searchword4...
                array_push($newsearchlines, $oldsearchline);
        }
        $oldsearchlines = $newsearchlines;
        //echo 'Lines containing ' . ($i + 1) . ' word:<br>';
        //var_dump($newsearchlines);
    }
    
    return $newsearchlines;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function getFirstWordLines($testlines, $test, $searchwords)//for dependent()
{
    $search1lines = array();
    foreach($testlines as $myline)
    {
        $readingword = true;
        foreach($myline as $mytoken)
        {
            $mytoken = trim(strtolower(preg_replace("#[[:punct:]]#", "", $mytoken)));
            if($readingword)
            {
                if ($mytoken != $searchwords[0])//searchword1
                    goto skipline;
                    
                $readingword = false;//proceed to harvest line numbers
            }
            else
                array_push($search1lines, $test[$mytoken - 1]);
        }
        skipline:
    }
    //echo 'Lines containing first word:<br>';
    //var_dump($search1lines);
    return $search1lines;
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////



///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function didYouMean($string) 
{
    $toreplace = array();
    $replacements = array();
    $tok = strtok($string, " :!\n\t");
    $pspell_link = pspell_new("en");                                            //use dictionary of ICD-10 words
    
    while ($tok !== false) 
    {
        if (!pspell_check($pspell_link, "$tok")) 
        {
            $suggestions = array_map('strtolower', pspell_suggest($pspell_link, "$tok"));
                
            array_push($toreplace, $tok);
            array_push($replacements,$suggestions[0]);
        }
        $tok = strtok(" :!\n\t");
    }

    for($i = 0; $i < count($toreplace); $i++)
    {
        if (ctype_upper($toreplace[$i][0]))
            $replacements[$i] = ucfirst($replacements[$i]);
    }
    echo str_ireplace($toreplace, $replacements, $string);
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////SPELL CHECKER