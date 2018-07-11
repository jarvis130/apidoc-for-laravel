<?php
namespace  Biggold\Apidoc\Descriptions\DefaultDesc;

use Biggold\Apidoc\ApiDocServiceProvider;
use Biggold\Apidoc\Exceptions\DocException;
use Biggold\Apidoc\Exceptions\DocIgnoreException;
use Biggold\Apidoc\Exceptions\UndefinedMarkException;
use Illuminate\Support\Str;

class ApiCodeCommentsParser {

    private $controllerClass;
    private $controllerMethod;

    private $markSet = ["param","name","description","response","paramTest","docIgnore","return"];

    public function __construct($uses) {

        $this->parseUses($uses);
        $this->markSet = array_unique((array_merge($this->markSet, $this->getConfigMarks())));

    }

    public function parseUses($uses) {
        if(!is_string($uses)) {
            throw new DocIgnoreException();
        }
        $result = Str::parseCallback($uses);
        $this->controllerClass = $result[0];
        $this->controllerMethod = $result[1];
    }

    public function getUses() {
        return $this->controllerClass."@".$this->controllerMethod;
    }

    public function parse() {
        $comment = $this->getComments();
        $commentArray = $this->prepareComment($comment);
        $result = [];
        foreach ($commentArray as $row) {
            $rowResult = $this->parseRowComment($row);
            if(isset($rowResult["param"])) {
                if(isset($result["param"])) {
                    $result["param"][] = $rowResult["param"];
                } else {
                    $result["param"] = [$rowResult["param"]];
                }
            } else {
                $result = array_merge($result, $rowResult);
            }
        }
        return $result;
    }

    public function parseRowComment($row) {
        if(strpos($row, "@docIgnore")===0) {
            throw new DocIgnoreException();
        }
        $fristSpaceIndex = strpos($row, " ");

        if($fristSpaceIndex===false) {
            //mark后直接换行的情况
            $fristSpaceIndex = strpos($row, "\n");
        } else {
            $fristnIndex = strpos($row, "\n");
            if($fristnIndex!==false) {
                $fristSpaceIndex = min($fristSpaceIndex,$fristnIndex);
            }
        }
        if($fristSpaceIndex===false) {
            //没有用空格分开标记和内容标记
            $mark = $row;
            $content = "";
        } else {
            if($fristSpaceIndex<2) {
                //标记内容为空的放弃
                throw new DocException("unanalyzable \"@ XXX\"(may be @XXX) in ".$this->getUses().":".$row);
            }
            $mark = substr($row, 0, $fristSpaceIndex);
            $content = substr($row, $fristSpaceIndex+1);
        }
        $mark = trim($mark, "@");
        if(!in_array($mark, $this->markSet)) {
            //放弃未定义的标记
            throw new UndefinedMarkException("undefined mark in ".$this->getUses().": @".$mark);
        }

        if($mark!="Param") {
            return [$mark=>$content];
        }

        //参数标记特殊处理
        if($content==="") {
            throw new DocException("@Param no content in ".$this->getUses().": ".$row);
        }
        $paramTestIndex = strpos($content, "@paramTest");
        if($paramTestIndex===false) {
            $paramTest = "";
            $paramDoc = trim($content);
        } else {
            $paramTest = substr($content, $paramTestIndex);
            $paramDoc = substr($content, 0, $paramTestIndex);
        }
        if(trim($paramDoc)==="") {
            throw new DocException("@Param no content in ".$this->getUses().": ".$row);
        }
        $contentFristSemicolonIndex = strpos($paramDoc, ":");
        $paramName = substr($content, 0 ,$contentFristSemicolonIndex);

        $paramValue = substr($paramDoc, $contentFristSemicolonIndex+1);
        $paramTestValue = str_replace("@paramTest", "", $paramTest);
        return [
            "param" => [
                "name" => $paramName,
                "value" => trim($paramValue),
                "paramTest" => trim($paramTestValue)
            ]
        ];
    }

    public function getComments() {
        $clazz = new \ReflectionClass($this->controllerClass);
        $method = $clazz->getMethod($this->controllerMethod);
        return $method->getDocComment();
    }

    public function prepareComment($comment) {
        $comment = str_replace("\r","\n",$comment);
        $commentArray = explode("\n",$comment);
        $tmpCommentArray = [];
        foreach ($commentArray as $line) {
            $line = trim($line,"/");
            $line = trim($line);
            $line = trim($line,"*");
            if($line!="") {
                $tmpCommentArray[] = $line;
            }
        }

        $commentArray = $tmpCommentArray;
        $tmpCommentArray = [];
        foreach ($commentArray as $line) {
            $trimLine = trim($line);
            if(strpos($trimLine,"@")===0) {
                $tmpCommentArray[] = $trimLine;
            } else {
                $tmpCommentArrayLastIndex = count($tmpCommentArray)-1;
                if($tmpCommentArrayLastIndex<0) {
                    //丢弃首行没有标记
                    continue;
                }
                //链接标记多行内容
                $tmpCommentArray[$tmpCommentArrayLastIndex] .= "\n".$line;
            }
        }
        return $tmpCommentArray;
    }

    public function addMark($markName) {
        if(isset($this->markSet[$markName])) {
            return;
        }
        $this->markSet[] = $markName;
    }

    private function getConfigMarks() {
        return ApiDocServiceProvider::getCustomMarks();
    }
}