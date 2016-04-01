<?php

namespace Concerto\PanelBundle\Service;

use Concerto\PanelBundle\Entity\ViewTemplate;
use Concerto\PanelBundle\Entity\User;
use Concerto\PanelBundle\Entity\AEntity;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class ViewTemplateService extends AExportableSectionService {

    public function get($object_id, $createNew = false) {
        $object = parent::get($object_id, $createNew);
        if ($createNew && $object === null) {
            $object = new ViewTemplate();
        }
        return $object;
    }

    public function save(User $user, $object_id, $name, $description, $accessibility, $protected, $archived, $owner, $groups, $html, $head) {
        $errors = array();
        $object = $this->get($object_id);
        $new = false;
        if ($object === null) {
            $object = new ViewTemplate();
            $new = true;
        }
        $object->setUpdated();
        $object->setUpdatedBy($user);
        if ($head !== null) {
            $object->setHead($head);
        }
        if ($html !== null) {
            $object->setHtml($html);
        }
        $object->setName($name);
        if ($description !== null) {
            $object->setDescription($description);
        }
        if(!$new && $object->isProtected() == $protected && $protected){
            array_push($errors, "validate.protected.mod");
        }
        
        if ($this->securityAuthorizationChecker->isGranted(User::ROLE_SUPER_ADMIN)) {
            $object->setAccessibility($accessibility);
            $object->setOwner($owner);
            $object->setGroups($groups);
        }
        
        $object->setProtected($protected);
        $object->setArchived($archived);
        foreach ($this->validator->validate($object) as $err) {
            array_push($errors, $err->getMessage());
        }
        if (count($errors) > 0) {
            return array("object" => null, "errors" => $errors);
        }
        $this->repository->save($object);
        return array("object" => $object, "errors" => $errors);
    }

    public function delete($object_ids) {
        $object_ids = explode(",", $object_ids);
        
        $result = array();
        foreach ($object_ids as $object_id) {
            $object = $this->get($object_id);
            if($object === null) continue;
            if ($object->isProtected()) {
                array_push($result, array("object" => $object, "errors" => array("validate.protected.mod")));
                continue;
            }
            $this->repository->delete($object);
            array_push($result, array("object" => $object, "errors" => array()));
        }
        return $result;
    }

    public function entityToArray(AEntity $ent) {
        $e = $ent->jsonSerialize();
        return $e;
    }

    public function importFromArray(User $user, $newName, $obj, &$map, &$queue) {
        $formattedName = $this->formatImportName($user, $newName, $obj);

        $ent = new ViewTemplate();
        $ent->setName($formattedName);
        $ent->setDescription($obj["description"]);
        $ent->setHead($obj["head"]);
        $ent->setHtml($obj["html"]);
        $ent->setGlobalId($obj["globalId"]);
        $ent->setOwner($user);
        $ent->setProtected($obj["protected"] == "1");
        $ent_errors = $this->validator->validate($ent);
        $ent_errors_msg = array();
        foreach ($ent_errors as $err) {
            array_push($ent_errors_msg, $err->getMessage());
        }
        if (count($ent_errors_msg) > 0) {
            return array("errors" => $ent_errors_msg, "entity" => null, "source" => $obj);
        }
        $this->repository->save($ent);
        
        if (!array_key_exists("ViewTemplate", $map)) {
            $map["ViewTemplate"] = array();
        }
        $map["ViewTemplate"]["id" . $obj["id"]] = $ent->getId();
        
        return array("errors" => null, "entity" => $ent);
    }
}
