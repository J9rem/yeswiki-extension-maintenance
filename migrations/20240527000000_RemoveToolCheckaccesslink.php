<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Plugins;

class RemoveToolCheckaccesslink extends YesWikiMigration
{
    public function run()
    {
        $this->removeOrDeactivateATool('checkaccesslink');
    }

    /**
     * remove or deactivate a tool
     * @param string $folderName
     */
    protected function removeOrDeactivateATool(string $folderName)
    {
        if (file_exists("tools/$folderName")) {
            if (!$this->shouldDeactivateInsteadOfDeleting($folderName)) {
                if ($this->deleteTool($folderName)) {
                    return ;
                } else {
                    throw new Exception("Folder 'tools/$folderName' not deleted !");
                }
            } elseif (!is_file("tools/$folderName/desc.xml")) {
                throw new Exception("Folder 'tools/$folderName' can not be deactivated : remove it manually !");
            } elseif (!$this->isActive($folderName)) {
                return ;
            } elseif ($this->deactivate($folderName)) {
                return ;
            } else {
                throw new Exception("Folder 'tools/$folderName' can not be deactivated : remove it manually !");
            }
        }
    }

    /**
     * retrieve info from desc file for tools
     * @param string $dirName
     * @return array
     */
    protected function getInfoFromDesc(string $dirName)
    {
        include_once 'includes/YesWikiPlugins.php';
        $pluginService = new Plugins('tools/');
        if (is_file("tools/$dirName/desc.xml")) {
            return $pluginService->getPluginInfo("tools/$dirName/desc.xml");
        }
        return [];
    }

    /**
     * test if on Windows and prefer deactive to prevent git folder to be deleted
     * @param string $folderName
     * @return bool
     */
    protected function shouldDeactivateInsteadOfDeleting(string $folderName): bool
    {
        return (DIRECTORY_SEPARATOR === '\\' && is_dir("tools/$folderName") && is_dir("tools/$folderName/.git"));
    }

    /**
     * check if a tool is active
     * @param string $folderName
     * @return bool
     */
    protected function isActive(string $folderName): bool
    {
        $info = $this->getInfoFromDesc($folderName);
        return  empty($info['active']) ? false : in_array($info['active'], [1,"1",true,"true"]);

    }

    /**
     * deactivate a tool
     * @param string $dirName
     * @return bool
     */
    protected function deactivate(string $dirName): bool
    {
        $xmlPath = "tools/$dirName/desc.xml";
        if (is_file($xmlPath)) {
            $xml = file_get_contents($xmlPath);
            $newXml = preg_replace("/(active=)\"([^\"]+)\"/", "$1\"0\"", $xml);
            if (!empty($newXml) && $newXml != $xml) {
                file_put_contents($xmlPath, $newXml);
                return !$this->isActive($dirName);
            }
        }
        return false;
    }

    /**
     * delete a tool
     * @param string $dirName
     * @return bool
     */
    protected function deleteTool(string $dirName): bool
    {
        return (!$this->delete("tools/$dirName"))
            ? false
            : !file_exists("tools/$dirName");
    }

    /**
     * delete a path
     * @param string $path
     * @return bool
     */
    protected function delete($path)
    {
        if (empty($path)) {
            return false;
        }
        if (is_file($path)) {
            if (unlink($path)) {
                return true;
            }
            return false;
        }
        if (is_dir($path)) {
            return $this->deleteFolder($path);
        }
    }

    /**
     * delete a folder by deleting recursively sub folders and files
     * @param string $path
     * @return bool
     */
    private function deleteFolder($path)
    {
        $file2ignore = array('.', '..');
        if (is_link($path)) {
            unlink($path);
        } else {
            if ($res = opendir($path)) {
                while (($file = readdir($res)) !== false) {
                    if (!in_array($file, $file2ignore)) {
                        $this->delete($path . '/' . $file);
                    }
                }
                closedir($res);
            }
            rmdir($path);
        }
        return true;
    }
}
