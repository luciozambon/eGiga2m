<?php

// ----------------------------------------------------------------
//
// php_xls.php
//
// emit an XML file readeble by Excel XP and 2003
//
// 23/01/2003 - RB - First release
// 14/04/2008 - LZ - added date/time management
//
// ----------------------------------------------------------------

class php_xls {
   var $FileName = "phpxls.xls";
   var $Author = "Nobody";
   var $LastAuthor = "Nobody";
   var $Company = "NoCompany";
   var $Data = array(array(0));
   var $OutFile = "";

   function SendFile()
   {
      $this->generate();
      header("Content-Disposition: attachment; filename=$this->FileName");
      header("Content-Type: application/x-msexcel");
      header("Content-Length: ".strlen($this->OutFile));
      echo $this->OutFile;
   }

   function is_date($string)
   {
     $dateSeparator = array('-', '/');
      return 
        is_numeric($string[0]) and
        is_numeric($string[1]) and
        is_numeric($string[2]) and
        is_numeric($string[3]) and
        in_array($string[4], $dateSeparator) and
        is_numeric($string[5]) and
        is_numeric($string[6]) and
        in_array($string[7], $dateSeparator) and
        is_numeric($string[8]) and
        is_numeric($string[9]) and
        $string[10]=='T' and
        is_numeric($string[11]) and
        is_numeric($string[12]) and
        $string[13]==':' and
        is_numeric($string[14]) and
        is_numeric($string[15]) and
        $string[16]==':' and
        is_numeric($string[17]) and
        is_numeric($string[18]);
   }

   function generate() {
      $TotalRow = count($this->Data);
      $TotalCol = count($this->Data[0]);
      $this->OutFile = "<?xml version=\"1.0\"?>
<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"
 xmlns:o=\"urn:schemas-microsoft-com:office:office\"
 xmlns:x=\"urn:schemas-microsoft-com:office:excel\"
 xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"
 xmlns:html=\"http://www.w3.org/TR/REC-html40\">
 <DocumentProperties xmlns=\"urn:schemas-microsoft-com:office:office\">
  <Author>".$this->Author."</Author>
  <LastAuthor>".$this->LastAuthor."</LastAuthor>
  <Created>2003-01-23T13:31:21Z</Created>
  <Company>".$this->Company."</Company>
  <Version>10.3501</Version>
 </DocumentProperties>
 <OfficeDocumentSettings xmlns=\"urn:schemas-microsoft-com:office:office\">
  <DownloadComponents/>
  <LocationOfComponents HRef=\"file:///C:\\\"/>
 </OfficeDocumentSettings>
 <ExcelWorkbook xmlns=\"urn:schemas-microsoft-com:office:excel\">
  <WindowHeight>4872</WindowHeight>
  <WindowWidth>7488</WindowWidth>
  <WindowTopX>288</WindowTopX>
  <WindowTopY>48</WindowTopY>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID=\"Default\" ss:Name=\"Normal\">
   <Alignment ss:Vertical=\"Bottom\"/>
   <Borders/>
   <Font/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID=\"s21\">
   <NumberFormat ss:Format=\"General Date\"/>
  </Style>
 </Styles>
 <Worksheet ss:Name=\"Sheet1\">
  <Table ss:ExpandedColumnCount=\"$TotalCol\" ss:ExpandedRowCount=\"$TotalRow\" x:FullColumns=\"1\" x:FullRows=\"1\" ss:DefaultRowHeight=\"13.2\">\n";
    for($i=0;$i<$TotalRow;$i++) {
      $this->OutFile .= "<Row>\n";
      for($j=0; $j<$TotalCol;$j++) {
         $Type = "String";
	 $format = "";
         if (is_numeric($this->Data[$i][$j]))
            $Type = "Number";
         if ($this->is_date($this->Data[$i][$j])) {
            $Type = "DateTime";
	    $format = " ss:StyleID=\"s21\"";
	 }   
         $this->OutFile.="<Cell$format><Data ss:Type=\"$Type\">".$this->Data[$i][$j]."</Data></Cell>\n";
      }
   $this->OutFile .="</Row>\n";
   }
	$this->OutFile .= "</Table>
  <WorksheetOptions xmlns=\"urn:schemas-microsoft-com:office:excel\">
   <PageSetup>
    <PageMargins x:Bottom=\"0.984251969\" x:Left=\"0.78740157499999996\"
     x:Right=\"0.78740157499999996\" x:Top=\"0.984251969\"/>
   </PageSetup>
   <Selected/>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
";
   }

}
?>
