#!/bin/bash

OUTPUT=''

# ideally, we should check all the following classes
# Association AssociationType AttributeGroup Attribute AttributeOption AttributeOptionValue AttributeRequirement Category
# Channel Completeness Currency Family Group Locale Metric Product ProductMedia ProductPrice ProductValue

for pattern in Attribute Category Completeness Product ProductValue; do
    OUTPUT=`find $1 -type f -name "*.php" | grep -v "/Tests/" | xargs grep -P "[^a-zA-Z']$pattern([ ',;)]|\$)" | grep -v "*" | grep -v "namespace" | grep -v "class $pattern" | tr "\n" "#"`$OUTPUT
done
if [ -z "$OUTPUT" ]; then
    exit 0
else
    echo -n $OUTPUT | tr "#" "\n"
    exit 1
fi
