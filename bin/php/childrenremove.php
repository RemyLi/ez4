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

$scriptOptions = $script->getOptions( "[nodes-id:][ignore-trash][classes:][dry-run][disable-indexing]",
                                      "",
                                      array( 'nodes-id' => "Subtree nodes ID (separated by comma ',').",
                                             'ignore-trash' => "Ignore trash ('move to trash' by default).",
                                          'classes' => "classes identifiers of object to delete (separated by comma ',').",
                                          'dry-run' => "Mode dry-run",
                                          'disable-indexing' => "disable-indexing",
                                             ),
                                      false );
$script->initialize();
$srcNodesID  = $scriptOptions[ 'nodes-id' ] ? trim( $scriptOptions[ 'nodes-id' ] ) : false;
$moveToTrash = $scriptOptions[ 'ignore-trash' ] ? false : true;
$disable_indexing = !!$scriptOptions[ 'disable-indexing' ];
$dryRun      = $scriptOptions[ 'dry-run' ] ? true : false;
$classes     = $scriptOptions[ 'classes' ] ? trim($scriptOptions[ 'classes' ]) : false;
$verbose     = $scriptOptions[ 'verbose' ];
$size        = 200;
$start       = time();

$deleteIDArray = $srcNodesID ? explode( ',', $srcNodesID ) : false;

if ( !$deleteIDArray ) {
    $cli->error( "Subtree remove Error!\nCannot get subtree nodes. Please check nodes-id argument and try again." );
    $script->showHelp();
    $script->shutdown( 1 );
}

$ini = eZINI::instance();
// Get user's ID who can remove subtrees. (Admin by default with userID = 14)
$userCreatorID = $ini->variable( "UserSettings", "UserCreatorID" );
/** @var eZUser $user */
$user = eZUser::fetch( $userCreatorID );
if ( !$user ) {
    $cli->error( "Subtree remove Error!\nCannot get user object by userID = '$userCreatorID'.\n(See site.ini[UserSettings].UserCreatorID)" );
    $script->shutdown( 1 );
}
eZUser::setCurrentlyLoggedInUser( $user, $userCreatorID );
$cli->notice( "Logged as ".$user->Login. " userID = '$userCreatorID' (See site.ini[UserSettings].UserCreatorID)" );

$params = array(
    'Depth' => 1,
    'Limit' => $size,
    'Offset' => 0,
);
if ($classes) {
    $params['ClassFilterType'] = 'include';
    $params['ClassFilterArray'] = explode(',',$classes);
    $cli->notice( "Classes filters : " . implode(', ', $params['ClassFilterArray']) );
}
//$cli->output(var_export($params, true));

if ($disable_indexing) {
    eZINI::instance()->setVariable('ContentSettings', 'ViewCaching', 'disabled');
    eZINI::instance()->setVariable('ContentSettings', 'StaticCache', 'disabled');
    eZINI::instance()->setVariable('ContentSettings', 'PreViewCache', 'disabled');
    eZINI::instance()->setVariable('SearchSettings', 'DelayedIndexing', 'enabled');
    eZINI::instance('ezfind.ini')->setVariable('IndexOptions', 'OptimizeOnCommit', 'disabled');
    $cli->notice( "indexing disabled. Don't forget to reindex content." );
}

if ($dryRun) {
    $cli->notice( "Don't worry, --dry-run mode activated." );
}

foreach ( $deleteIDArray as $nodeID )
{
    /** @var eZContentObjectTreeNode $node */
    $node = eZContentObjectTreeNode::fetch( $nodeID );
    if ( $node === null )
    {
        $cli->error( "\nSubtree remove Error!\nCannot find subtree with nodeID: '$nodeID'." );
        continue;
    }

    $count = (int)eZContentObjectTreeNode::subTreeCountByNodeID($params, $nodeID);

    $cli->notice( "===== $nodeID : $count children to remove. ".$node->url() );

    $i = 0;
    do {
        /** @var eZContentObjectTreeNode[] $children */
        $children = eZContentObjectTreeNode::subTreeByNodeID($params, $nodeID);

        foreach ($children as $child) {
            $i++;

            $child_node_id = $child->attribute('node_id') ;
            if ($verbose) {
                $t = time();
                $te = max($t-$start, 1); // temps écoulé. (1 min pour éviter les divisions par 0)
                $tm = ($te/$i); // temps moyen. = temps déjà consomé divisé par le nombre de objet passé. FLoat.
                $r = $count-$i;
                $tr = (int)($r * $tm); // temps restant = nombre d'objet restant multiplié par le temp moyen.
                $tf = $t + $tr; // temps final = temps actuel plus le temps restant
                $df = date('H:i.s', $tf); // date finalle
                /*
                $cli->output(var_export(array(
                    '$count' => $count,
                    '$start' => $start,
                    '$t' => $t,
                    '$i' => $i,
                    '$te' => $te,
                    '$tm' => $tm,
                    '$r' => $r,
                    '$tr' => $tr,
                    '$tf' => $tf,
                    '$df' => $df,
                ), true));
                */

                $i_out = str_pad($i, strlen("$count"));
                $cli->output( "[$i_out/$count] Remove node id : $child_node_id. estimated end : $df ".$child->url() );
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
        if ($dryRun) { // Si il n'y a pas de dry-run alors la liste des résultats se décale d'elle même.
            $params['Offset'] += $size; // Avec le dry-run on est obligé de décaler l'indexe car les éléments ne sont pas supprimé.
        }
    } while (count($children) > 0 && $i <= $count);
}

$cli->output( "===== Done." );
$script->shutdown();

?>
