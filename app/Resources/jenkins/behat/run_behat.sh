#!/bin/bash

#
# Execute Behat features in parallel
# The supporting DBs must exists and be available before executing the script.
# Same thing for the Behat profiles
#

XDEBUG_EXTENSION="xdebug.so"
CHECK_WAIT=2

usage() {
    echo "Usage: $0 (concurrency) (xdebug|noxdebug) (database_prefix) (profile_prefix) [behat command and options]"
    echo "(feature file name will automatically appended)"
    exit 1;
}

if [ $# -lt 5 ] ; then
    usage
else
    CONCURRENCY=$1
    XDEBUG=$2
    DB_PREFIX=$3
    PROFILE_PREFIX=$4
    BEHAT_CMD=`echo $* | sed -e "s/$CONCURRENCY//" -e "s/$XDEBUG//" -e "s/$DB_PREFIX//" -e "s/$PROFILE_PREFIX//"`

    if [ $XDEBUG != 'xdebug' ] && [ $XDEBUG != 'noxdebug' ] ; then
        echo "Invalid xdebug parameter [$XDEBUG]"
        usage
    fi

    expr $CONCURRENCY + 0 > /dev/null 2>&1
    if [ $? != 0 ]; then
        echo "Invalid concurrency parameter [$CONCURRENCY]"
        usage
    fi
fi

ORIGINAL_DB_NAME=`echo $DB_PREFIX | sed -e "s/_$//"`

FEATURES_DIR=`dirname $0`/../../../../features

if [ "$XDEBUG" = 'xdebug' ]; then
    PHP_EXTENSION_DIR=`php -i | grep extension_dir | cut -d ' ' -f3`
    if [ ! -f $PHP_EXTENSION_DIR/$XDEBUG_EXTENSION ]; then
       echo "Unable to find xdebug extension $PHP_EXTENSION_DIR/$XDEBUG_EXTENSION"
       exit 2
    fi
fi

if [ "$XDEBUG" = 'xdebug' ]; then
    BEHAT_CMD="php -d zend_extension=$PHP_EXTENSION_DIR/$XDEBUG_EXTENSION $BEHAT_CMD"
fi

export DISPLAY=:0

FEATURES_NAMES=""

# Install the assets and db on all environments
cd $FEATURES_DIR/..
sed -i -e 's/database_name:.*$/database_name: "%database.name%"/' app/config/parameters_test.yml
for PROC in `seq 1 $CONCURRENCY`; do
    export SYMFONY__DATABASE__NAME=$DB_PREFIX$PROC
    cp app/config/config_behat.yml app/config/config_behat$PROC.yml
    mysqldump -u root $ORIGINAL_DB_NAME | mysql -u root $DB_PREFIX$PROC
    eval PID_$PROC=0
done
cd -

FEATURES=`find $FEATURES_DIR/ -name *.feature`
for FEATURE in $FEATURES; do
    
    FEATURE_NAME=`echo $FEATURE | sed -e 's#^.*/features/\(.*\)$#features/\1#'`

    while [ ! -z $FEATURE_NAME ]; do

        for PROC in `seq 1 $CONCURRENCY`; do
            # Make sure there's a feature to process
            if [ ! -z $FEATURE_NAME ]; then

                # Check if processus PID_$PROC is available
                # (/proc/PID should not exist)
                PID_VARNAME=PID_$PROC
                PID="${!PID_VARNAME}"
                ls /proc/$PID > /dev/null 2>&1
                if [ $? -ne 0 ]; then
                    export SYMFONY__DATABASE__NAME=$DB_PREFIX$PROC
                    echo "Executing feature $FEATURE_NAME"
                    $BEHAT_CMD --profile=$PROFILE_PREFIX$PROC $FEATURE_NAME &
                    RESULT=$!
                    eval PID_$PROC=$RESULT
                    FEATURE_NAME=""
                fi

            fi
        done

        # There's a feature waiting to be executed, but no processus
        # available. Wait a little bit before checking again
        if [ ! -z $FEATURE_NAME ]; then
            sleep $CHECK_WAIT
        fi
    done
done


