<?php

namespace BYanelli\Roma\Request\Data;

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

class PhpDocTypeParser
{
    private function parsePhpDoc(string $phpDoc): PhpDocNode
    {
        $config = new ParserConfig(usedAttributes: []);
        $lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $parser = new PhpDocParser($config, $typeParser, $constExprParser);

        $tokens = new TokenIterator($lexer->tokenize($phpDoc));

        return $parser->parse($tokens);
    }

    private function getArrayTypePhpDocNode(ReflectionParameter|ReflectionProperty $obj): TypeNode
    {
        if ($obj instanceof ReflectionParameter) {
            $constructorPhpDoc = $obj->getDeclaringClass()
                ?->getConstructor()
                ?->getDocComment();

            if ($constructorPhpDoc === null) {
                throw new RuntimeException("{$obj->getName()} must be declared in a constructor");
            }

            if ($constructorPhpDoc === false) {
                throw new RuntimeException('Constructor params with array types must be documented in PHPDoc');
            }

            $parsedPhpDoc = $this->parsePhpDoc($constructorPhpDoc);

            /** @var ParamTagValueNode $phpDocPropertyNode */
            $phpDocPropertyNode = collect($parsedPhpDoc->getParamTagValues())
                ->first(fn (ParamTagValueNode $param) => $param->parameterName == '$'.$obj->getName())
                ?: throw new RuntimeException("Constructor param {$obj->getName()} not found in PHPDoc");

            return $phpDocPropertyNode->type;
        } else {
            $propertyPhpDoc = $obj->getDocComment();

            if ($propertyPhpDoc === false) {
                throw new RuntimeException('Properties with array type must be documented by @var in PHPDoc');
            }

            $parsedPhpDoc = $this->parsePhpDoc($propertyPhpDoc);

            /** @var VarTagValueNode $phpDocPropertyNode */
            $phpDocPropertyNode = collect($parsedPhpDoc->getVarTagValues())
                ->first()
                ?: throw new RuntimeException("Property {$obj->getName()} @var tag not found in PHPDoc");

            return $phpDocPropertyNode->type;
        }
    }

    private function parseArrayElementTypeNameFromPhpDocNode(TypeNode $node): string
    {
        preg_match(
            pattern: '/array<(\w+)>/',
            subject: $node->__toString(),
            matches: $matches,
            flags: PREG_OFFSET_CAPTURE
        );

        return $matches[1][0] ?? throw new RuntimeException("Error parsing array element type from type declaration: $node");
    }

    public function getArrayElementTypeName(ReflectionParameter|ReflectionProperty $obj): string
    {
        return $this->parseArrayElementTypeNameFromPhpDocNode($this->getArrayTypePhpDocNode($obj));
    }
}
