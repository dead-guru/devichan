<?php





class Archive {
    
    
    // Archive thread and replies
    static public function archiveThread($thread_id) {
        global $config, $board;
        
        // If archiving is turned off return
        if(!$config['archive']['threads'])
            return;
        
        // Check if it is a thread
        $thread_query = prepare(sprintf("SELECT `thread`, `subject`, `body_nomarkup`, `trip` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
        $thread_query->bindValue(':id', $thread_id, PDO::PARAM_INT);
        $thread_query->execute() or error(db_error($thread_query));
        $thread_data = $thread_query->fetch(PDO::FETCH_ASSOC);
        
        if($thread_data['thread'] !== NULL)
            error($config['error']['invalidpost']);
        
        // Create Snippet of thread text
        $thread_data['snippet_body'] = strtok($thread_data['body_nomarkup'], "\r\n");
        $thread_data['snippet_body'] = substr($thread_data['snippet_body'], 0, $config['archive']['snippet_len'] - strlen($thread_data['subject']));
        archive_list_markup($thread_data['snippet_body']);
        $thread_data['snippet'] = '<b>' . $thread_data['subject'] . '</b> ';
        $thread_data['snippet'] .= $thread_data['snippet_body'];
        
        // Select thread and replies in one query
        $query = prepare(sprintf("SELECT `id`,`thread`,`files`,`slug` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id", $board['uri']));
        $query->bindValue(':id', $thread_id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));
        
        // List of files associated with thread
        $file_list = array();
        
        while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
            // Copy Static HTML page for Thread
            if (!$post['thread']) {
                // Read Content of HTML
                $thread_file_content = @file_get_contents($board['dir'] . $config['dir']['res'] . link_for($post));
                
                // Replace links and posting mode to Archived
                $thread_file_content = str_replace(sprintf('src="/' . $config['board_path'], $board['uri']), sprintf('src="/' . $config['board_path'] . $config['dir']['archive'], $board['uri']), $thread_file_content);
                $thread_file_content = str_replace(sprintf('href="/' . $config['board_path'], $board['uri']), sprintf('href="/' . $config['board_path'] . $config['dir']['archive'], $board['uri']), $thread_file_content);
                $thread_file_content = str_replace('Posting mode: Reply', 'Archived thread', $thread_file_content);
                // Remove Post Form from HTML (First Form)
                $thread_file_content = preg_replace("/<form name=\"post\"(.*?)<\/form>/i", "", $thread_file_content);
                
                // Refix archive link that will be wrong
                $thread_file_content = str_replace(sprintf('href="/' . $config['board_path'] . $config['dir']['archive'] . $config['dir']['archive'], $board['uri']), sprintf('href="/' . $config['board_path'] . $config['dir']['archive'], $board['uri']), $thread_file_content);
                
                // Remove Form from HTML
                $thread_file_content = preg_replace("/<form(.*?)>/i", "", $thread_file_content);
                $thread_file_content = preg_replace("/<\/form>/i", "", $thread_file_content);
                $thread_file_content = preg_replace("/<input (.*?)>/i", "", $thread_file_content);
                
                // Remove Redundant code from HTML
                $thread_file_content = preg_replace("/<div id=\"report\-fields\"(.*?)<\/div>/i", "", $thread_file_content);
                $thread_file_content = preg_replace("/<div id=\"thread\-interactions\"(.*?)<\/div>/i", "", $thread_file_content);
                
                // Write altered thread HTML to archive location
                @file_put_contents($board['dir'] . $config['dir']['archive'] . $config['dir']['res'] . sprintf($config['file_page'], $thread_id), $thread_file_content, LOCK_EX);
            }
            
            
            // Copy json file to Archive
            // Read Content of Json file
            $json_file_content = @file_get_contents($board['dir'] . $config['dir']['res'] . json_scrambler($thread_id, true));
            // Replace links and posting mode to Archived
            $json_file_content = str_replace(substr($board['dir'], 0, -1) . '\/' . substr($config['dir']['res'], 0, -1), substr($board['dir'], 0, -1) . '\/' . substr($config['dir']['archive'], 0, -1) . '\/' . substr($config['dir']['res'], 0, -1), $json_file_content);
            // Write altered thread json to archive location
            @file_put_contents($board['dir'] . $config['dir']['archive'] . $config['dir']['res'] .  json_scrambler($thread_id, true), $json_file_content, LOCK_EX);
            
            
            // Copy Images and Files Associated with Thread
            if ($post['files']) {
                foreach (json_decode($post['files']) as $i => $f) {
                    if ($f->file !== 'deleted') {
                        @copy($board['dir'] . $config['dir']['img'] . $f->file, $board['dir'] . $config['dir']['archive'] . $config['dir']['img'] . $f->file);
                        @copy($board['dir'] . $config['dir']['thumb'] . $f->thumb, $board['dir'] . $config['dir']['archive'] . $config['dir']['thumb'] . $f->thumb);
                        
                        $file_list[] = $f;
                        
                        // $file_list[$i]['file'] = $f->file;
                        // $file_list[$i]['thumb'] = $f->thumb;
                    }
                }
            }
        }
        
        // Insert Archive Data in Database
        $query = prepare(sprintf("INSERT INTO ``archive_%s`` VALUES (:thread_id, :snippet, :lifetime, :files, 0, 0, 0)", $board['uri']));
        $query->bindValue(':thread_id', $thread_id, PDO::PARAM_INT);
        $query->bindValue(':snippet', $thread_data['snippet'], PDO::PARAM_STR);
        $query->bindValue(':lifetime', 	time(), PDO::PARAM_INT);
        $query->bindValue(':files', json_encode($file_list));
        $query->execute() or error(db_error($query));
        
        
        // Check if Thread should be Auto Featured based on OP Trip
        if(in_array($thread_data['trip'], $config['archive']['auto_feature_trips']))
            self::featureThread($thread_id);
        
        
        // Purge Threads that have timed out
        if(!$config['archive']['cron_job']['purge'])
            self::purgeArchive();
        
        // Rebuild Archive Index
        self::buildArchiveIndex();
        
        return true;
    }
    
    
    
    
    
    // Removes Archived Threads that has outlived their lifetime
    static public function purgeArchive() {
        global $config, $board;
        
        // If archive is set to live forever return
        if(!$config['archive']['lifetime'])
            return;
        
        // Delete all static pages and files for archived threads that has timed out
        $query = prepare(sprintf("SELECT `id`, `files` FROM ``archive_%s`` WHERE `lifetime` < :lifetime AND `featured` = 0 AND `mod_archived` = 0", $board['uri']));
        $query->bindValue(':lifetime', strtotime("-" . $config['archive']['lifetime']), PDO::PARAM_INT);
        $query->execute() or error(db_error($query));
        while($thread = $query->fetch(PDO::FETCH_ASSOC)) {
            // Delete Files
            foreach (json_decode($thread['files']) as $f) {
                @unlink($board['dir'] . $config['dir']['archive'] . $config['dir']['img'] . $f->file);
                @unlink($board['dir'] . $config['dir']['archive'] . $config['dir']['img'] . $f->thumb);
            }
            
            // Delete Thread
            @unlink($board['dir'] . $config['dir']['archive'] . $config['dir']['res'] . sprintf($config['file_page'], $thread['id']));
        }
        
        // Delete Archive Entries
        if($query->rowCount() != 0) {
            $query = prepare(sprintf("DELETE FROM  ``archive_%s`` WHERE `lifetime` < :lifetime AND `featured` = 0 AND `mod_archived` = 0", $board['uri'])) or error(db_error());
            $query->bindValue(':lifetime', strtotime("-" . $config['archive']['lifetime']), PDO::PARAM_INT);
            $query->execute() or error(db_error($query));
            
            modLog(sprintf("Purged %d archived threads due to expiration date", $query->rowCount()));
        }
        
        return $query->rowCount();
    }
    
    
    
    
    
    
    // Feature thread and replies
    static public function featureThread($thread_id, $mod_archive = false) {
        global $config, $board, $mod;
        
        // If featuring of threads is turned off return
        if(!$mod_archive && !$config['feature']['threads'])
            return;
        // If mod archive of threads is turned off return
        if($mod_archive && !$config['mod_archive']['threads'])
            return;
        
        $query = query(sprintf("SELECT `files` FROM ``archive_%s`` WHERE `id` = %d AND " . ($mod_archive?"`mod_archived`":"`featured`") . " = 0", $board['uri'], (int)$thread_id)) or error(db_error());
        if(!$thread = $query->fetch(PDO::FETCH_ASSOC))
            error($config['error']['invalidpost']);
        
        // Read Content of HTML
        $thread_file_content = @file_get_contents($board['dir'] . $config['dir']['archive'] . $config['dir']['res'] . sprintf($config['file_page'], $thread_id));
        
        // Replace links and posting mode to Archived
        $thread_file_content = str_replace(sprintf('src="/' . $config['board_path'] . $config['dir']['archive'], $board['uri']), sprintf('src="/' . $config['board_path'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']), $board['uri']), $thread_file_content);
        $thread_file_content = str_replace(sprintf('href="/' . $config['board_path'] . $config['dir']['archive'], $board['uri']), sprintf('href="/' . $config['board_path'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']), $board['uri']), $thread_file_content);
        $thread_file_content = str_replace('Archived thread', 'Featured thread', $thread_file_content);
        
        // Write altered thread HTML to archive location
        @file_put_contents($board['dir'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']) . $config['dir']['res'] . sprintf($config['file_page'], $thread_id), $thread_file_content, LOCK_EX);
        
        foreach (json_decode($thread['files']) as $f) {
            @copy($board['dir'] . $config['dir']['archive'] . $config['dir']['img'] . $f->file, $board['dir'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']) . $config['dir']['img'] . $f->file);
            @copy($board['dir'] . $config['dir']['archive'] . $config['dir']['thumb'] . $f->thumb, $board['dir'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']) . $config['dir']['thumb'] . $f->thumb);
        }
        
        // Update DB entry
        query(sprintf("UPDATE ``archive_%s`` SET " . ($mod_archive?"`mod_archived`":"`featured`") . " = 1 WHERE `id` = %d", $board['uri'], (int)$thread_id)) or error(db_error());
        
        // Add mod log entry
        modLog(sprintf("Added thread #%d to " . ($mod_archive?"mod archive":"featured threads"), $thread_id));
        
        
        // Rebuild Featured Index
        self::buildFeaturedIndex();
        // Rebuild Archive Index
        self::buildArchiveIndex();
        
        return true;
    }
    
    
    
    
    
    
    
    static public function deleteFeatured($thread_id, $mod_archive = false) {
        global $config, $board, $mod;
        
        $query = query(sprintf("SELECT `id`, `files`, `lifetime` FROM ``archive_%s`` WHERE `featured` = 1 OR `mod_archived` = 1", $board['uri'])) or error(db_error());
        if(!$thread = $query->fetch(PDO::FETCH_ASSOC))
            error($config['error']['invalidpost']);
        
        
        // Delete Files
        foreach (json_decode($thread['files']) as $f) {
            @unlink($board['dir'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']) . $config['dir']['img'] . $f->file);
            @unlink($board['dir'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']) . $config['dir']['img'] . $f->thumb);
        }
        
        // Delete Thread
        @unlink($board['dir'] . ($mod_archive?$config['dir']['mod_archive']:$config['dir']['featured']) . $config['dir']['res'] . sprintf($config['file_page'], $thread_id));
        
        // Delete Entry in DB if it has timed out
        if($thread['lifetime'] != 0 && $thread['lifetime'] < strtotime("-" . $config['archive']['lifetime']))
            query(sprintf("DELETE FROM ``archive_%s`` WHERE `id` = %d AND " . ($mod_archive?"`featured`":"`mod_archived`") . " = 0", $board['uri'], (int)$thread_id)) or error(db_error());
        else
            query(sprintf("UPDATE ``archive_%s`` SET " . ($mod_archive?"`mod_archived`":"`featured`") . " = 0 WHERE `id` = %d", $board['uri'], (int)$thread_id)) or error(db_error());
        
        // Add mod log entry
        modLog(sprintf("Deleted thread #%d from " . ($mod_archive?"mod archive":"featured threads"), $thread_id));
        
        // Rebuild Featured Index
        self::buildFeaturedIndex();
        // Rebuild Archive Index
        self::buildArchiveIndex();
    }
    
    
    
    static public function RebuildArchiveIndexes() {
        global $config;
        
        // If archiving is turned off return
        if(!$config['archive']['threads'])
            return;
        
        // Purge Archive
        if(!$config['archive']['cron_job']['purge'])
            self::purgeArchive();
        
        // Rebuild Archive Index
        self::buildArchiveIndex();
        
        // Rebuild Featured Index
        self::buildFeaturedIndex();
        
    }
    
    
    static public function buildArchiveIndex() {
        global $config, $board;
        
        // If archiving is turned off return
        if(!$config['archive']['threads'])
            return;
        
        // Get archive List
        $archive = self::getArchiveList();
        
        foreach($archive as &$thread)
            $thread['archived_url'] = $config['dir']['res'] . sprintf($config['file_page'], $thread['id']);
        
        $title = sprintf(_('Archived') . ' %s: ' . $config['board_abbreviation'], _('threads'), $board['uri']);
        $archive_page = Element('page.html', array(
            'config' => $config,
            'mod' => false,
            'hide_dashboard_link' => true,
            'boardlist' => createBoardList(false),
            'title' => $title,
            'subtitle' => "",
            'body' => Element("mod/archive_list.html", array(
                'config' => $config,
                'thread_count' => count($archive),
                'board' => $board,
                'archive' => $archive
            ))
        ));
        
        file_write($config['dir']['home'] . $board['dir'] . $config['dir']['archive'] . $config['file_index'], $archive_page);
    }
    
    
    
    static public function getArchiveList($featured = false, $mod_archive = false, $order_by_lifetime = false) {
        global $config, $board;
        
        $archive = false;
        if($featured) {
            $query = query(sprintf("SELECT `id`, `snippet`, `featured`, `mod_archived` FROM ``archive_%s`` WHERE `featured` = 1", $board['uri']) . ($order_by_lifetime?" ORDER BY `lifetime` DESC":" ORDER BY `id` DESC")) or error(db_error());
            $archive = $query->fetchAll(PDO::FETCH_ASSOC);
        } else if($mod_archive) {
            $query = query(sprintf("SELECT `id`, `snippet`, `featured`, `mod_archived` FROM ``archive_%s`` WHERE `mod_archived` = 1", $board['uri']) . ($order_by_lifetime?" ORDER BY `lifetime` DESC":" ORDER BY `id` DESC")) or error(db_error());
            $archive = $query->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $query = prepare(sprintf("SELECT `id`, `snippet`, `featured`, `mod_archived` FROM ``archive_%s`` WHERE `lifetime` > :lifetime", $board['uri']) . ($order_by_lifetime?" ORDER BY `lifetime` DESC":" ORDER BY `id` DESC"));
            $query->bindValue(':lifetime', strtotime("-" . $config['archive']['lifetime']), PDO::PARAM_INT);
            $query->execute() or error(db_error());
            $archive = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $archive;
    }
    
    
    
    static public function buildFeaturedIndex() {
        global $config, $board;
        
        // If featuring of threads is turned off return
        if(!$config['feature']['threads'])
            return;
        
        // Get featured archived threads
        $archive = self::getArchiveList(true);
        
        foreach($archive as &$thread)
            $thread['featured_url'] = $config['dir']['res'] . sprintf($config['file_page'], $thread['id']);
        
        $title = sprintf(_('Featured') . ' %s: ' . $config['board_abbreviation'], _('threads'), $board['uri']);
        $archive_page = Element('page.html', array(
            'config' => $config,
            'mod' => false,
            'hide_dashboard_link' => true,
            'boardlist' => createBoardList(false),
            'title' => $title,
            'subtitle' => "",
            'body' => Element("mod/archive_featured_list.html", array(
                'config' => $config,
                'board' => $board,
                'archive' => $archive
            ))
        ));
        
        file_write($config['dir']['home'] . $board['dir'] . $config['dir']['featured'] . $config['file_index'], $archive_page);
    }
}
