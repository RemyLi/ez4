#!/usr/bin/env php
<?php

/**
 * Children Remove Script
 *
 * php extention/meddispardesign/bin/php/childrenremove.php --nodes-id=XXX --ignore-trash --class=CLASS_NAME
 */

// script initializing
require 'autoload.php';

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "\n" .
                                                         "This script will make a remove of a content object children.\n" ),
                                      'use-session' => false,
                                      'use-modules' => true,
                                      'use-extensions' => true ) );
$script->startup();

$scriptOptions = $script->getOptions( "[nodes-id:][ignore-trash][classes:][dry-run]",
                                      "",
                                      array( 'nodes-id' => "Subtree nodes ID (separated by comma ',').",
                                             'ignore-trash' => "Ignore trash ('move to trash' by default).",
                                          'classes' => "classes identifiers of object to delete (separated by comma ',').",
                                          'dry-run' => "Mode dry-run"
                                             ),
                                      false );
$script->initialize();
$srcNodesID  = $scriptOptions[ 'nodes-id' ] ? trim( $scriptOptions[ 'nodes-id' ] ) : false;
$moveToTrash = $scriptOptions[ 'ignore-trash' ] ? false : true;
$dryRun      = $scriptOptions[ 'dry-run' ] ? true : false;
$classes     = $scriptOptions[ 'classes' ] ? trim($scriptOptions[ 'classes' ]) : false;
$verbose     = $scriptOptions[ 'verbose' ];
$size        = 200;

$deleteIDArray = $srcNodesID ? explode( ',', $srcNodesID ) : false;

if ( !$deleteIDArray )
{
    $cli->error( "Subtree remove Error!\nCannot get subtree nodes. Please check nodes-id argument and try again." );
    $script->showHelp();
    $script->shutdown( 1 );
}

$ini = eZINI::instance();
// Get user's ID who can remove subtrees. (Admin by default with userID = 14)
$userCreatorID = $ini->variable( "UserSettings", "UserCreatorID" );
$user = eZUser::fetch( $userCreatorID );
if ( !$user )
{
    $cli->error( "Subtree remove Error!\nCannot get user object by userID = '$userCreatorID'.\n(See site.ini[UserSettings].UserCreatorID)" );
    $script->shutdown( 1 );
}
eZUser::setCurrentlyLoggedInUser( $user, $userCreatorID );

$params = array(
    'Depth' => 1,
    'Limit' => $size,
    'Offset' => 0,
);
if ($classes) {
    $params['ClassFilterType'] = 'include';
    $params['ClassFilterArray'] = explode(',',$classes);
}
$cli->output(var_export($params, true));

foreach ( $deleteIDArray as $nodeID )
{
    /** @var eZContentObjectTreeNode $node */
    $node = eZContentObjectTreeNode::fetch( $nodeID );
    if ( $node === null )
    {
        $cli->error( "\nSubtree remove Error!\nCannot find subtree with nodeID: '$nodeID'." );
        continue;
    }

    $count = eZContentObjectTreeNode::subTreeCountByNodeID($params, $nodeID);

    $cli->notice( "===== $nodeID : $count children to remove. ".$node->url() );

    $i = 0;
    do {
        /** @var eZContentObjectTreeNode[] $children */
        $children = eZContentObjectTreeNode::subTreeByNodeID($params, $nodeID);

        foreach ($children as $child) {
            $i++;

            $child_node_id = $child->attribute('node_id') ;
            if ($verbose) {
                $cli->output( "[$i/$count] $child_node_id remove. ".$child->url() );
            } else {
                $cli->output('-', false);
            }
            // Remove subtrees
            if (!$dryRun) {
                eZContentObjectTreeNode::removeSubtrees(array($child_node_id), $moveToTrash);
            }

            //TODO
//            // We should make sure that all subitems have been removed.
//            $itemInfo = eZContentObjectTreeNode::subtreeRemovalInformation( array( $nodeID ) );
//            $itemTotalChildCount = $itemInfo['total_child_count'];
//            $itemDeleteList = $itemInfo['delete_list'];
//
//            if ( count( $itemDeleteList ) != 0 or ( $childCount != 0 and $itemTotalChildCount != 0 ) )
//                $cli->error( "\nWARNING!\nSome subitems have not been removed.\n" );
//            else
//                $cli->output( "Successfuly DONE.\n" );
        }
        $params['offset'] += $size;
    } while (count($children) > 0);
}

$cli->output( "===== Done." );
$script->shutdown();

?>
